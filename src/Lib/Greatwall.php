<?php
/**
 * @description 长城支付
 * 
 * @see https://pan.baidu.com/s/1kVQT6xp
 */

namespace Zilch\Lib;

use Zilch\Base;
use Zilch\Constants;
use GuzzleHttp\Client;

class Greatwall extends Base
{
    /**
     * pay
     *
     * @param string $orderid 商户订单号
     * @param string $total_amount 支付总金额 (单位/分)
     * @param string $payway 支付途径
     * @param string $paymethod 支付方式 `wap` or `scan`
     */
    public function pay($orderid = '', $total_amount = 0, $payway = 1, $extParams = [])
    {
        $paymethod = isset($extParams['paymethod']) ? $extParams['paymethod'] : 'wap';
        $payUrlConfig = array(
            'scan' => '/passivePay',
            'wap' => '/wapPay',
        );
        // request url
        $extUrl = isset($payUrlConfig[$paymethod]) ? $payUrlConfig[$paymethod] : $paymethod;
        $gatewayUrl = $this->_gatewayUrl . $extUrl;
        // 金额格式化
        $total_amount = $total_amount / 100;
        $totalamount = sprintf('%.2f', $total_amount);
        // 支付类型
        $payType = $this->_getPayway($payway);
        $reqData = array(
            'merchno' => $this->_appid,
            'amount' => $totalamount,
            'traceno' => $orderid,
            'payType' => $payType,
            'goodsName' => iconv('UTF-8', 'GB2312', '游戏币'),
            'notifyUrl' => $this->_notifyUrl,
        );
        $strToSign = $this->_combineStrToSign($reqData, 'req');
        $signature = $this->_generateSign($strToSign);
        $reqStr = $strToSign . 'signature=' . $signature;
        if ($this->_debug) {
            file_put_contents('/tmp/zlog_for_gwpay_create_data.log', $reqStr . "\n", FILE_APPEND);
            file_put_contents('/tmp/zlog_for_gwpay_request_url.log', $gatewayUrl . "\n", FILE_APPEND);
        }
        // 发送请求
        $guzzleClient = new Client([
            'base_uri' => $gatewayUrl,
            'timeout'  => 6.0,
        ]);
        $response = $guzzleClient->request('POST', '', [
            'body' => $reqStr,
        ]);
        if (200 != $response->getStatusCode()) {
            throw new \Exception('网络发生错误：' . $response->getReasonPhrase());
        }
        $responseBody = $response->getBody()->getContents();
        $result = iconv('GB2312', 'UTF-8', $responseBody);
        $resultArr = json_decode($result, true);
        if (is_array($resultArr)) {
            if ($this->_debug) {
                file_put_contents('/tmp/zlog_for_gwpay_create_data_return.log', var_export($resultArr, true), FILE_APPEND);
            }
            if (isset($resultArr['respCode'])) {
                if (00 == $resultArr['respCode']) {
                    return [
                        'code' => 0,
                        'data' => $resultArr['barCode'],
                    ];
                }
            }
        }
        return [
            'code' => 1,
            'msg' => $resultArr['message'],
        ];
    }

    /**
     * 验签方法
     * @param $data 支付成功后返回的数据
     * @return boolean
     */
    public function verify($data = array())
    {
        if (!$data) {
            $data = $_POST;
        }
        if ($this->_debug) {
            file_put_contents('/tmp/zlog_for_gwpay_notify_data.log', var_export($data, true), FILE_APPEND);
        }
        if (1 == $data['status']) {
            if ($this->_appid == $data['merchno']) {
                $sign = $data['sign'];
                // 校验
                $strToSign = $this->_combineStrToSign($data, 'check');
                $mySign = $this->_generateSign($strToSign);
                // $mySign = strtolower($mySign);
                if ($this->_debug) {
                    file_put_contents('/tmp/zlog_for_gwpay_check_sign.log', date('Y-m-d H:i:s') . '--' . $sign . ' == ' . $mySign . "\n", FILE_APPEND);
                }
                if ($sign == $mySign) {
                    echo 'success';
                    return [
                        'orderid' => $data['traceno'],
                        // 第三方返回金额为元, 转换为分
                        'paymoney' => $data['amount'] * 100,
                        // 第三方平台订单号
                        'tradeno' => $data['orderno'],
                    ];
                }
            }
        }
        echo 'fail';
        return false;
    }

    /**
     * 组装参数
     *
     * @param array $params 请求参数
     * @param string $type 类型 `req` 发送请求 `check` 校验服务器的返回数据
     */
    protected function _combineStrToSign($params = array(), $type = 'req')
    {
        if (!$params) {
            if ($this->_debug) {
                file_put_contents('/tmp/zlog_for_gwpay_generate_sign_params_error.log', var_export($params, true), FILE_APPEND);
            }
            return false;
        }
        if (isset($params['sign'])) {
            unset($params['sign']);
        }
        ksort($params);
        $str = '';
        foreach ((array) $params as $key => $value) {
            $trimVal = trim($value);
            if ($trimVal !== '') {
                $str .= sprintf('%s=%s&', $key, $trimVal);
                // if ('req' == $type) {
                //     $str .= $key . '=' . iconv('UTF-8', 'GB2312', $trimVal) . '&';
                // } else {
                //     $str .= sprintf('%s=%s&', $key, $trimVal);
                // }
            }
        }
        if ($this->_debug) {
            file_put_contents('/tmp/zlog_for_gwpay_str_to_sign.log', date('Y-m-d H:i:s') . '--' . $type . '--' . $str . "\n", FILE_APPEND);
        }
        return $str;
    }

    /**
     * 生成校验 sign
     */
    protected function _generateSign($str)
    {
        $str .= $this->_appkey;
        $sign = md5($str);
        if ($this->_debug) {
            $debugData = [
                'str_to_sign' => $str,
                'sign' => $sign,
            ];
            file_put_contents('/tmp/zlog_for_gwpay_generate_sign_data.log', var_export($debugData, true), FILE_APPEND);
        }
        return $sign;
    }

    /**
     * 转换 payway 为该支付平台可识别的字符串
     */
    protected function _getPayway($payway = 0)
    {
        if (Constants::ALIWAP == $payway) {
            return 1;
        }
        if (Constants::WXWAP == $payway) {
            return 2;
        }
        if (Constants::QQWAP == $payway) {
            return 4;
        }
        if (Constants::BAIDU == $payway) {
            return 10;
        }
        if (Constants::JDPAY == $payway) {
            return 11;
        }
        return $payway;
    }

}
