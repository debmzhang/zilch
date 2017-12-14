<?php
/**
 * 微派支付 https://www.wiipay.cn/
 *
 * @see https://www.wiipay.cn/docs/#/wpay
 */

namespace Zilch\Lib;

use Zilch\Base;
use Zilch\Constants;

class Wiipay extends Base
{
    /**
     * 组合请求参数
     *
     * @param int $money 支付金额(单位/分)
     * @param string $orderid 订单
     * @param int $payway 支付方式
     */
    public function pay($money = 0, $orderid = '', $payway = 1, $extParams = [])
    {
        $payway = $this->_getPayway($payway);
        $money = intval($money);
        // 金额单位转换(分 => 元)
        $money = $money / 100;
        $money = sprintf('%.2f', $money);
        if (!$payway) {
            $payway = 'default';
        }
        // 生成 sign 的参数
        $params = [
            'app_id' => $this->_appid,
            'body' => $this->_body,
            'callback_url' => $this->_returnUrl,
            'channel_id' => $payway,
            'out_trade_no' => $orderid,
            'total_fee' => $money,
            'version' => $this->_version,
        ];
        $sign = $this->_generateSign($params);
        // 返回的参数
        $reqParams = [
            'url' => $this->_gatewayUrl . 'appId=' . $this->_appid,
            'body' => $this->_body,
            'callback_url' => $this->_returnUrl,
            'channel_id' => $payway,
            'out_trade_no' => $orderid,
            'total_fee' => $money,
            'version' => $this->_version,
            'sign' => $sign,
            'debug' => $this->_debug,
        ];
        return [
            'code' => 0,
            'data' => $reqParams,
        ];
    }

    /**
     * 校验回调数据
     *
     * @param array $data 需校验的数据
     */
    public function verify($data = [])
    {
        if (!$data) {
            $data = $_POST;
        }
        // 商户参数/商户订单号
        $cpparam = $data["cpparam"];
        // 微派订单号
        $orderNo = $data["orderNo"];
        // 价格 单位元，精确到两位小数
        $price = $data["price"];
        // 签名串
        $sign = $data["sign"];
        // 交易状态 success成功，fail失败
        $status = $data["status"];
        // 交易类型 alipay支付宝;wxpay微信;upmp银联;qqpayQQ钱包;transfer企业代付
        $synType = $data["synType"];
        // 时间戳
        $time = $data["time"];
        // params
        $pre_arr = [
            "cpparam" => $cpparam,
            "orderNo" => $orderNo,
            "price" => $price,
            "status" => $status,
            "synType" => $synType,
            "time" => $time,
        ];
        $sing_result = $this->_generateSign($pre_arr);
        if ($sing_result == $sign) {
            if ($status == 'success') {
                // 验签成功，返回纯文本success
                echo 'success';
                return [
                    'orderid' => $cpparam,
                    // 第三方返回金额为元, 转换为分
                    'paymoney' => $price * 100,
                    // 第三方平台订单号
                    'tradeno' => $orderNo,
                ];
            }
        } else {
            echo 'bad sign';
            return false;
        }
        echo 'bad request';
        return false;
    }

    /**
     * 生成 sign
     */
    protected function _generateSign($pre_arr = null)
    {
        if (!$pre_arr) {
            return false;
        }
        // 按字母序
        ksort($pre_arr);
        $pre_str = implode('&', array_map(
            function($k, $v) {
                return sprintf("%s=%s", $k, $v);
            },
            array_keys($pre_arr),
            $pre_arr
        ));
        $preSign = $pre_str . $this->_appkey;
        $sign = strtoupper(md5($preSign));
        if ($this->_debug) {
            $debugData = [
                'str_to_sign' => $preSign,
                'sign' => $sign,
            ];
            file_put_contents('/tmp/zlog_for_wiipay_generate_sign.log', var_export($debugData, true), FILE_APPEND);
        }
        return $sign;
    }

    /**
     * 转换 payway 为该支付平台可识别的字符串
     */
    protected function _getPayway($payway = 0)
    {
        if (Constants::ALIWAP == $payway) {
            return 'ali';
        }
        if (Constants::WXWAP == $payway) {
            return 'wx';
        }
        if (Constants::QQWAP == $payway) {
            return 'qq';
        }
        if (Constants::UNIONPAY == $payway) {
            return 'un';
        }
        return 'default';
    }

}
