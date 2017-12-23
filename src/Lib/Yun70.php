<?php
/**
 * @description 云70支付
 *
 * @see https://pan.baidu.com/s/1gfpOzr9
 * @see password h9ir
 */

namespace Zilch\Lib;

use Zilch\Base;
use Zilch\Constants;

class Yun70 extends Base
{
    /**
     * pay
     *
     * @param string $orderid 商户订单号
     * @param string $total_amount 支付总金额 (单位/分)
     * @param string $payway 支付类型
     */
    public function pay($total_amount = 0, $orderid = '', $payway = '', $extParams = [])
    {
        if (!$orderid || !$total_amount) {
            return false;
        }
        // 金额格式化
        $total_amount = $total_amount / 100;
        $totalamount = sprintf('%.2f', $total_amount);
        if ($totalamount == intval($totalamount)) {
            $totalamount = intval($totalamount);
        }
        // 支付类型标识
        $type = $this->_getPayway($payway);
        // 校验 sign
        $signParams = array(
            'paytype' => $type,
            'paymoney' => $totalamount,
            'orderid' => $orderid,
        );
        $sign = $this->_generateSign($signParams, 'create');
        $reqData = array(
            'parter' => $this->_appid,
            'type' => $type,
            'value' => $total_amount,
            'orderid' => $orderid,
            'callbackurl' => $this->_notifyUrl,
            'attach' => 'game icon',
            'hrefbackurl' => $this->_returnUrl,
            'payerIp' => isset($extParams['ip']) ? $extParams['ip'] : '127.0.0.1',
            'sign' => $sign,
        );
        $reqParams = http_build_query($reqData);
        $reqParams = urldecode($reqParams);
        $url = $this->_gatewayUrl . '?' . $reqParams;
        if ($this->_debug) {
            file_put_contents('/tmp/zlog_for_yun70_debug_header_url.log', date('Y-m-d H:i:s') . '--' . $url . "\n", FILE_APPEND);
        }
        return [
            'code' => 0,
            'data' => $url,
        ];
    }

    /**
     * 验签方法
     * @param $data 异步通知的数据
     * @return boolean
     */
    public function verify($data = [])
    {
        if (!$data) {
            $data = $_GET;
        }
        if ($this->_debug) {
            file_put_contents('/tmp/zlog_for_yun70_notify_data.log', var_export($data, true), FILE_APPEND);
        }
        $orderid = $data['orderid'];
        $opstate = $data['opstate'];
        $paymoney = $data['ovalue'];
        $attach = $data['attach'];
        $sign = $data['sign'];
        if (0 == $opstate) {
            $validSignParams = array(
                'orderid' => $orderid,
                'opstate' => $opstate,
                'paymoney' => $paymoney,
            );
            $validSign = $this->_generateSign($validSignParams, 'notify');
            if ($sign == $validSign) {
                echo 'opstate=0';
                return [
                    'orderid' => $data['orderid'],
                    // 第三方返回金额为元, 转换为分
                    'paymoney' => $data['ovalue'] * 100,
                    // 第三方平台订单号
                    'tradeno' => $data['sysorderid'],
                ];
            }
        }
        echo 'opstate=1';
        return false;
    }

    /**
     * description 生成校验sign
     *
     * @param array $params 参与加密的参数
     * @param string $type 生成sign的类型(create创建订单 notify回调)
     */
    protected function _generateSign($params = array(), $type = 'create')
    {
        if ('create' == $type) {
            $paytype = $params['paytype'];
            $paymoney = $params['paymoney'];
            $orderid = $params['orderid'];
            $strToSign = sprintf('parter=%s&type=%d&value=%s&orderid=%s&callbackurl=%s%s', $this->_appid, $paytype, $paymoney, $orderid, $this->_notifyUrl, $this->_appkey);
        } elseif ('notify' == $type) {
            $orderid = $params['orderid'];
            $opstate = $params['opstate'];
            $paymoney = $params['paymoney'];
            $strToSign = sprintf('orderid=%s&opstate=%d&ovalue=%s%s', $orderid, $opstate, $paymoney, $this->_appkey);
        }
        if ($this->_debug) {
            file_put_contents('/tmp/zlog_for_yun70_str_to_sign_str.log', date('Y-m-d H:i:s') . '--' . $strToSign . "\n", FILE_APPEND);
        }
        return md5($strToSign);
    }

    /**
     * 转换 payway 为该支付平台可识别的字符串
     */
    protected function _getPayway($payway = '')
    {
        // 支付平台已处理过 payway 标识, 可直接返回;
        return $payway;
        if (Constants::ALISCAN == $payway) {
            return 992;
        }
        if (Constants::ALIWAP == $payway) {
            return 1003;
        }
        if (Constants::WXSCAN == $payway) {
            return 1004;
        }
        if (Constants::WXAPP == $payway) {
            return 1005;
        }
        if (Constants::WXWAP == $payway) {
            return 1007;
        }
        if (Constants::QQSCAN == $payway) {
            return 1008;
        }
        if (Constants::QQWAP == $payway) {
            return 1009;
        }
        if (Constants::JDSCAN == $payway) {
            return 1010;
        }
        if (Constants::JDWAP == $payway) {
            return 1011;
        }
        if (Constants::BDSCAN == $payway) {
            return 1012;
        }
        if (Constants::BDWAP == $payway) {
            return 1013;
        }
        return $payway;
    }

}
