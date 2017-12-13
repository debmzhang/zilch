<?php

namespace Zilch;

class Base
{
    protected $_mchid;
    protected $_appid;
    protected $_appkey;
    protected $_gatewayUrl;
    protected $_notifyUrl;
    protected $_returnUrl;
    protected $_body;
    protected $_subject;
    protected $_version;
    protected $_signType;
    protected $_returnFormat;
    protected $_debug;

    /**
     * description
     */
    public function __construct($config = array())
    {
        $this->_mchid = isset($config['mchid']) ? $config['mchid'] : '';
        $this->_appid = isset($config['appid']) ? $config['appid'] : '';
        $this->_appkey = isset($config['appkey']) ? $config['appkey'] : '';
        $this->_gatewayUrl = isset($config['gateway_url']) ? $config['gateway_url'] : '';
        $this->_notifyUrl = isset($config['notify_url']) ? $config['notify_url'] : '';
        $this->_returnUrl = isset($config['return_url']) ? $config['return_url'] : '';
        $this->_body = isset($config['body']) ? $config['body'] : '';
        $this->_subject = isset($config['subject']) ? $config['subject'] : '';
        $this->_version = isset($config['version']) ? $config['version'] : '';
        $this->_signType = isset($config['sign_type']) ? $config['sign_type'] : '';
        $this->_returnFormat = isset($config['return_format']) ? $config['return_format'] : '';
        $this->_debug = isset($config['debug']) ? $config['debug'] : 0;
    }

}
