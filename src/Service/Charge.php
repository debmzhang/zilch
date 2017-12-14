<?php

namespace Zilch\Service;

use Zilch\Service;

class Charge extends Service
{
    /**
     * 请求支付
     *
     * @param string $type 支付平台类别
     * @param string $payway 支付方式
     * @param array $config 支付配置
     * @param array $data 业务数据
     */
    public function run($type = '', $payway = '', $config = [], $data = [])
    {
        $instance = self::getInstance($type, $config);
        $extParams = isset($data['ext_params']) ? $data['ext_params'] : [];
        // 金额 `paymoney` 单位(分)
        return $instance->pay($data['paymoney'], $data['orderid'], $payway, $extParams);
    }

}
