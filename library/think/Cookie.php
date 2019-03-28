<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
namespace think;

use Swoole\Http\Response;

/**
 * Swoole Cookie类
 */
class Cookie extends CookieBase
{
    protected static $response;

    /**
     * Cookie初始化
     * @access public
     * @param  array $config
     * @return void
     */
    public static function init(array $config = [])
    {
        self::$config = array_merge(self::$config, array_change_key_case($config));
    }

    public static function setResponse(Response $response)
    {
        self::$response = $response;
    }

    /**
     * Cookie 设置保存
     *
     * @access public
     * @param  string $name  cookie名称
     * @param  mixed  $value cookie值
     * @param  array  $option 可选参数
     * @return void
     */
    protected static function setCookie($name, $value, $expire, $option = [])
    {
        self::$response->cookie($name, $value, $expire, $option['path'], $option['domain'], $option['secure'], $option['httponly']);
    }
}
