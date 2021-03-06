<?php

namespace Zilch\Service;

use Zilch\Service;

class Notify extends Service
{
    /**
     * 处理支付通知数据
     *
     * @param string $type 支付平台类别
     * @param array $config 支付配置
     * @param array $data 异步通知数据
     */
    public function run($type = '', $config = [], $notifyData = [])
    {
        $instance = self::getInstance($type, $config);
        return $instance->verify($notifyData);
    }

}
