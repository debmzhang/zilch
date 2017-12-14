<?php
/**
 * @description E时代
 *
 * @see https://www.showdoc.cc/zy275848566?page_id=15326309
 */

namespace Zilch\Lib;

use Zilch\Base;
use Zilch\Constants;
use GuzzleHttp\Client;

class Etime extends Base
{
    /**
     * pay
     *
     * @param string $orderid 商户订单号
     * @param string $total_amount 支付总金额 (单位/分)
     * @param int $payway 支付类型
     */
    public function pay($orderid = '', $total_amount = 0, $payway = 1, $extParams = [])
    {
        // 金额格式, 必须有小数点, 且小数点后面保留两位
        $total_amount = $total_amount / 100;
        $totalamount = sprintf('%.2f', $total_amount);
        $service = $this->_getPayway($payway);
        $reqData = [
            'service' => $service,
            'version' => 'v1.0',
            'signtype' => $this->_signType,
            'charset' => 'UTF-8',
            'merchantid' => $this->_appid,
            'shoporderId' => $orderid,
            'totalamount' => $totalamount,
            'productname' => 'gameicon',
            // 商品标识 原样返回 (50)
            // 'goods_tag' => '',
            // 设备终端号/门店编号
            // 'storenumber' => '',
            'notify_url' => $this->_notifyUrl,
            'callback_url' => $this->_returnUrl,
            'mch_create_ip' => isset($extParams['ip']) ? $extParams['ip'] : '127.0.0.1',
            // 用户 openid，调用微信公众号原生js支付时此参数必须填写
            // 'sub_openid' => '',
            // 公众号appid，调用微信公众号原生js支付时此参数必须填写
            // 'sub_appid' => '',
            // 扫码支付授权码， 设备读取用户付款码信息，刷卡支付时必须填写
            // 'auth_code' => '',
            // 原样返回
            // 'remarks' => '',
            'nonce_str' => time() . uniqid(),
            // 限定用户使用时能否使用信用卡，1，禁用信用卡；0或者不传此参数则不禁用
            // 'credit_pay' => '',
            // 微信H5支付必填，现填固定值WAP
            'pagetype' => 'WAP',
            // 官网名称 微信H5支付必填
            'pagename' => 'ZCDTN',
            // 官网首页 微信H5支付必填
            'pageurl' => 'http://www.zcdtn.com/',
        ];
        $reqData['sign'] = $this->_generateSign($reqData);
        if ($this->_debug) {
            file_put_contents('/tmp/zlog_for_etime_create_data.log', var_export($reqData, true), FILE_APPEND);
        }
        // 发送请求
        $guzzleClient = new Client([
            'base_uri' => $this->_gatewayUrl,
            'timeout'  => 6.0,
        ]);
        $response = $guzzleClient->request('POST', '', [
            'json' => $reqData,
            'headers' => [
                'Content-Type' => 'application/json;charset=utf-8',
            ],
        ]);
        if (200 != $response->getStatusCode()) {
            throw new \Exception('网络发生错误：' . $response->getReasonPhrase());
        }
        $responseBody = $response->getBody()->getContents();
        $resultArr = json_decode($responseBody, true);
        if (is_array($resultArr)) {
            if ($this->_debug) {
                file_put_contents('/tmp/zlog_for_etime_create_data_return.log', var_export($resultArr, true), FILE_APPEND);
            }
            if (0 == $resultArr['result_code'] && 0 == $resultArr['status']) {
                if (isset($resultArr['code_url'])) {
                    return $resultArr['code_url'];
                }
            }
        }
        return false;
    }

    /**
     * 验签方法
     * @param $data 支付成功后返回的数据
     * @return boolean
     */
    public function verify($data = [])
    {
        if (!$data) {
            $data = file_get_contents('php://input');
            $data = json_decode($data, true);
        }
        if ($this->_debug) {
            file_put_contents('/tmp/zlog_for_etime_notify_data.log', var_export($data, true), FILE_APPEND);
        }
        if (0 == $data['result_code'] && 0 == $data['status']) {
            if ($this->_appid == $data['merchantid']) {
                $sign = $data['sign'];
                // 校验
                $mySign = $this->_generateSign($data);
                // $mySign = strtolower($mySign);
                if ($this->_debug) {
                    file_put_contents('/tmp/zlog_for_etime_check_sign.log', date('Y-m-d H:i:s') . '--' . $sign . ' == ' . $mySign . "\n", FILE_APPEND);
                }
                if ($sign == $mySign) {
                    echo 'SUCCESS';
                    return [
                        'orderid' => $data['shoporderId'],
                        // 第三方返回金额为元, 转换为分
                        'paymoney' => $data['total_fee'] * 100,
                        // 第三方平台订单号
                        'tradeno' => $data['orderid'],
                    ];
                }
            }
        }
        echo 'FAILURE';
        return false;
    }

    /**
     * description 生成校验 sign
     */
    protected function _generateSign($params = [])
    {
        if (!$params) {
            if ($this->_debug) {
                file_put_contents('/tmp/zlog_for_etime_generate_sign_params_error.log', var_export($params, true), FILE_APPEND);
            }
            return false;
        }
        ksort($params);
        $str = '';
        if (isset($params['sign'])) {
            unset($params['sign']);
        }
        foreach ((array) $params as $key => $value) {
            $trimVal = trim($value);
            if ($trimVal !== '') {
                $str .= sprintf('%s=%s&', $key, $trimVal);
            }
        }
        $str .= 'key=' . $this->_appkey;
        if ($this->_debug) {
            file_put_contents('/tmp/zlog_for_etime_str_to_sign.log', date('Y-m-d H:i:s') . '--' . $str . "\n", FILE_APPEND);
        }
        $sign = md5($str);
        if ($this->_debug) {
            $debugData = [
                'str_to_sign' => $str,
                'sign' => $sign,
            ];
            file_put_contents('/tmp/zlog_for_etime_generate_sign_data.log', var_export($debugData, true), FILE_APPEND);
        }
        return strtoupper($sign);
    }

    /**
     * 转换 payway 为该支付平台可识别的字符串
     */
    protected function _getPayway($payway = 0)
    {
        if (Constants::ALIWAP == $payway) {
            return 'HFAliPayH5';
        }
        if (Constants::WXWAP == $payway) {
            return 'YYKWXWAP';
        }
        if (Constants::WXSCAN == $payway) {
            return 'sdwxpay';
        }
        if (Constants::QQWAP == $payway) {
            return 'YHXFQQWAP';
        }
        return $payway;
    }

}
