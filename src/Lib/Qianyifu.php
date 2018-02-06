<?php
/**
 * @description qianyifu
 * 
 * @see http://www.qianyifu.com/
 */

namespace Zilch\Lib;

use Zilch\Base;
use Zilch\Constants;

class Qianyifu extends Base
{
    /**
     * pay
     *
     * @param string $total_amount 支付总金额 (单位/分)
     * @param string $orderid 商户订单号
     * @param int $payway 支付类型
     */
    public function pay($total_amount = 0, $orderid = '', $payway = '', $extParams = [])
    {
        // 金额单位转换(分 => 元)
        $money = $total_amount / 100;
        $money = sprintf('%.2f', $money);
        // 产品ID
        $productid = $this->_getPayway($payway);
        // sign
        $generateSignParams = array(
            'userid' => $this->_appid,
            'orderid' => $orderid,
            'bankid' => $productid,
            'keyvalue' => $this->_appkey,
        );
        $sign = $this->_generateSign($generateSignParams, 'req');
        $reqData = array(
            'userid' => $this->_appid,
            'orderid' => $orderid,
            'money' => $money,
            'url' => $this->_notifyUrl,
            'hrefurl' => $this->_returnUrl,
            'bankid' => $productid,
            'sign' => $sign,
            'ext' => '',
        );
        if ($this->_debug) {
            file_put_contents('/tmp/zlog_for_qianyifu_reqdata.log', var_export($reqData, true), FILE_APPEND);
        }
        $reqDataStr = http_build_query($reqData);
        $url = $this->_gatewayUrl . '?' . $reqDataStr;
        if ($this->_debug) {
            $rstDbgData = array(
                'url' => $url,
                'req_str' => $reqDataStr,
            );
            file_put_contents('/tmp/zlog_for_qianyifu_create_data_result.log', var_export($rstDbgData, true), FILE_APPEND);
        }
        return [
            'code' => 0,
            'data' => $url,
        ];
    }

    /**
     * 校验通知数据
     * 
     * @param $data 支付成功后返回的数据
     * @return boolean
     */
    public function verify($data = array())
    {
        if (!$data) {
            $data = $_GET;
        }
        // 我方订单号
        $orderno = $data['orderid'];
        // 用户实际支付金额(单位/元)
        $totalfee = $data['money'];
        $attach = $data['ext'];
        // 接收到的sign
        $receiveSign = $data['sign'];
        // sign
        $generateSignParams = array(
            'returncode' => $data['returncode'],
            'userid' => $this->_appid,
            'orderid' => $data['orderid'],
            'money' => $data['money'],
            'keyvalue' => $this->_appkey,
        );
        $generateSign = $this->_generateSign($generateSignParams, 'notify');
        if ($this->_debug) {
            $debugData = array(
                'p_data' => $data,
                'rsign' => $receiveSign,
                'gsign' => $generateSign,
            );
            file_put_contents('/tmp/zlog_for_qianyifu_notify_data_sign_error.log', var_export($debugData, true), FILE_APPEND);
        }
        if ($receiveSign == $generateSign) {
            echo 'success';
            return [
                'orderid' => $data['orderid'],
                // 第三方返回金额为元, 转换为分
                'paymoney' => $data['money'] * 100,
                // 第三方平台订单号(此平台不返回平台方订单号, 这里把 orderid 拿过来凑数)
                'tradeno' => $data['orderid'],
            ];
        }
        echo 'fail';
        return false;
    }

    /**
     * description 生成 sign
     *
     * @param array $params 参与签名的参数
     * @param string $scene 生成sign的场景 req 表示发送请求数据时; check 表示查询订单; notify 表示接收第三方通知时
     */
    protected function _generateSign($params = array(), $scene = 'req')
    {
        $strToSign = http_build_query($params);
        $sign = md5($strToSign);
        if ($this->_debug) {
            $debugData = array(
                'str_to_sign' => $strToSign,
                'sign' => $sign,
                'scene' => $scene,
            );
            file_put_contents('/tmp/zlog_for_generate_qianyifu_sign_data.log', var_export($debugData, true), FILE_APPEND);
        }
        return $sign;
    }

    /**
     * 转换 payway 为该支付平台可识别的字符串
     */
    protected function _getPayway($payway = '')
    {
        // 支付平台已处理过 payway 标识, 可直接返回;
        return $payway;
        if (Constants::ALIWAP == $payway) {
            return 'zhifubao-wap';
        }
        if (Constants::WXWAP == $payway) {
            return 'weixin-wap';
        }
        if (Constants::QQWAP == $payway) {
            return 'qq-wap';
        }
        if (Constants::ALISCAN == $payway) {
            return 'zhifubao';
        }
        if (Constants::WXSCAN == $payway) {
            return 'weixin';
        }
        if (Constants::QQSCAN == $payway) {
            return 'qqsm';
        }
        return 'default';
    }

}
