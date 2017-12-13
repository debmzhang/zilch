<?php
/**
 * 对外提供的服务
 */

namespace Zilch;

use Zilch\Constants;

class Service
{
    /**
     * description
     *
     * @param string $type 支付平台类别
     * @param array $config 支付配置
     */
    public function getInstance($type = '', $config = [])
    {
        $instance = null;
        if (Constants::WIIPAY == $type) {
            $instance = new \Zilch\Lib\Wiipay($config);
        }
        if (Constants::HUOHUOTUAN == $type) {
            $instance = new \Zilch\Lib\Huohuotuan($config);
        }
        return $instance;
    }

}
