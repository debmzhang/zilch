<?php

namespace Zilch\Service;

use Zilch\Service;

class Notify extends Service
{
    /**
     * 处理支付通知数据
     *
     * @param string $type 支付平台类别
     * @param string $payway 支付方式
     * @param array $config 支付配置
     * @param array $data 支付传递数据
     */
    public function run($type = '', $payway = '', $config = [], $data = [])
    {
    }

}
