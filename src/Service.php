<?php
/**
 * 对外提供的服务
 */

namespace Zilch;

class Service
{
    // 实例容器
    private static $_instances = array();

    /**
     * description
     *
     * @param string $type 支付平台类别
     * @param array $config 支付配置
     */
    public static function getInstance($type = '', $config = [])
    {
        if (!$type || !$config) {
            return null;
        }
        $hashString = $type . http_build_query($config);
        $hash = md5($hashString);
        if (!isset($_instance[$hash])) {
            $className = 'Zilch\Lib\\' . ucfirst($type);
            self::$_instance[$hash] = new $className($config);
        }
        return self::$_instance[$hash];
    }

}
