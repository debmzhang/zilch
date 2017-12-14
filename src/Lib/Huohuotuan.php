<?php
/**
 * huohuotuan
 *
 * @see http://m.huohuotuan.cn/
 */

namespace Zilch\Lib;

use Zilch\Base;
use Zilch\Constants;
use GuzzleHttp\Client;

class Huohuotuan extends Base
{
    /**
     * pay
     *
     * @param int $money 支付金额(单位/分)
     * @param string $orderid 订单
     * @param int $payway 支付方式
     */
    public function pay($money = 0, $orderid = '', $payway = 1, $extParams = [])
    {
        $payway = $this->_getPayway($payway);
        $money = intval($money);
        // 金额单位（分）
        $paytypeConf = [
            'wxwap' => 'wxwap',
            'wxscan' => 'wxqrcode',
            'wxh5' => 'wxhtml',
            'aliwap' => 'aliwap',
            'aliscan' => 'aliqrcode',
        ];
        $paytype = isset($paytypeConf[$payway]) ? $paytypeConf[$payway] : $payway;
        $reqData = [
            'orderid' => $orderid,
            'paymoney' => $money,
            'paytype' => $paytype,
        ];
        $sign = $this->_generateSign($reqData, 'request');
        $requestParams = $this->_generateRequestParams($money, $sign, $orderid, $paytype);
        // 发送请求
        $reqUrl = $this->_gatewayUrl . '?' . $requestParams;
        $guzzleClient = new Client([
            'base_uri' => $reqUrl,
            'timeout'  => 6.0,
        ]);
        $response = $guzzleClient->request('GET');
        if (200 != $response->getStatusCode()) {
            throw new \Exception('网络发生错误：' . $response->getReasonPhrase());
        }
        $responseBody = $response->getBody()->getContents();
        $resultArr = json_decode($responseBody, true);
        if (is_array($resultArr)) {
            if ($this->_debug) {
                file_put_contents('/tmp/zlog_for_huohuotuan_create_data_return.log', var_export($resultArr, true), FILE_APPEND);
            }
        }
        return [
            'code' => 0,
            'data' => $responseBody,
        ];
    }

    /**
     * 校验回调数据
     * 
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
            file_put_contents('/tmp/zlog_for_huohuotuan_notify_data.log', var_export($data, true), FILE_APPEND);
        }
        if (1 == $data['status']) {
            if ($this->_appid == $data['mch']) {
                $sign = $data['sign'];
                // 校验
                $mySign = $this->_generateSign($data);
                $notifyData = [
                    'orderid' => $data['order_id'],
                    'sysorder' => $data['orderNo'],
                    'paymoney' => $data['money'],
                    'paytype' => $data['pay_type'],
                    'timestamp' => $data['time'],
                ];
                $mySign = $this->_generateSign($notifyData, 'notify');
                if ($this->_debug) {
                    file_put_contents('/tmp/zlog_for_etime_check_sign.log', date('Y-m-d H:i:s') . '--' . $sign . ' == ' . $mySign . "\n", FILE_APPEND);
                }
                if ($sign == $mySign) {
                    echo 'SUCCESS';
                    return [
                        'orderid' => $data['order_id'],
                        // 单位(分)
                        'paymoney' => $data['money'],
                        // 第三方平台订单号
                        'tradeno' => $data['orderNo'],
                    ];
                }
            }
        }
        echo 'ERROR';
        return false;
    }

    /**
     * description 生成校验 sign
     *
     * @param array $params 参数
     * @param string $scene 请求场景 request 请求支付参数 notify 第三方支付应答
     */
    protected function _generateSign($params = [], $scene = 'request')
    {
        if (isset($params['sign'])) {
            unset($params['sign']);
        }
        if (!$params) {
            if ($this->_debug) {
                file_put_contents('/tmp/zlog_for_huohuotuan_generate_sign_params_error.log', var_export($params, true), FILE_APPEND);
            }
            return false;
        }
        if ('request' == $scene) {
            $format = '%s%d%s%d%s%s';
            $strToSign = sprintf($format, $params['orderid'], $params['paymoney'], $params['paytype'], time(), $this->_appid, md5($this->_appkey));
        }
        if ('notify' == $scene) {
            $format = '%s%s%d%s%s%d%s';
            $strToSign = sprintf($format, $params['orderid'], $params['sysorder'], $params['paymoney'], $this->_appid, $params['paytype'], $params['timestamp'],  md5($this->_appkey));
        }
        if ($this->_debug) {
            file_put_contents('/tmp/zlog_for_huohuotuan_str_to_sign.log', date('Y-m-d H:i:s') . '--' . $scene . '--' . $strToSign . "\n", FILE_APPEND);
        }
        $sign = md5($strToSign);
        if ($this->_debug) {
            $debugData = [
                'str_to_sign' => $strToSign,
                'sign' => $sign,
            ];
            file_put_contents('/tmp/zlog_for_huohuotuan_generate_sign_data.log', var_export($debugData, true), FILE_APPEND);
        }
        return $sign;
    }

    /**
     * description
     */
    protected function _generateRequestParams($paymoney = 300, $sign = '', $orderid = '', $paytype = '')
    {
        $format = 'mch=%s&key=%s&money=%d&time=%d&sign=%s&order_id=%s&return_url=%s&notify_url=%s&pay_type=%s';
        $queryStr = sprintf($format, $this->_appid, $this->_appkey, $paymoney, time(), $sign, $orderid, $this->_returnUrl, $this->_notifyUrl, $paytype);
        if ($this->_debug) {
            file_put_contents('/tmp/zlog_for_huohuotuan_generate_requestparams_data.log', $queryStr . "\n", FILE_APPEND);
        }
        return $queryStr;
    }

    /**
     * 转换 payway 为该支付平台可识别的字符串
     */
    protected function _getPayway($payway = 0)
    {
        if (Constants::ALIWAP == $payway) {
            return 'aliwap';
        }
        if (Constants::ALISCAN == $payway) {
            return 'aliscan';
        }
        if (Constants::WXWAP == $payway) {
            return 'wxwap';
        }
        if (Constants::WXSCAN == $payway) {
            return 'wxscan';
        }
        return $payway;
    }

}
