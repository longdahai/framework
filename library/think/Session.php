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

use Swoole\Table;

/**
 * Swoole Cookie类
 */
class Session extends SessionBase
{
    protected static $config = [];
    /**
     * Session数据
     * @var array
     */
    protected static $data = [];

    /**
     * 记录Session name
     * @var string
     */
    protected static $sessionName = 'PHPSESSID';

    /**
     * Session有效期
     * @var int
     */
    protected static $expire = 0;

    /**
     * Swoole_table对象
     * @var Table
     */
    protected static $swooleTable;

    /**
     * session初始化
     * @access public
     * @param  array $config
     * @return void
     * @throws \think\Exception
     */
    public static function init(array $config = [])
    {
        if (empty($config)) {
            $config = self::$config = Config::get('session');
        }

        if (!empty($config['name'])) {
            self::$sessionName = $config['name'];
        }

        if (!empty($config['expire'])) {
            self::$expire = $config['expire'];
        }

        if (!empty($config['auto_start'])) {
            self::start();
        } else {
            self::$init = false;
        }
    }

    /**
     * session自动启动或者初始化
     * @access public
     * @return void
     */
    public static function boot()
    {
        if (is_null(self::$init)) {
            self::init();
        }

        if (false === self::$init) {
            self::start();
        }
    }

    public static function name($name)
    {
        self::$sessionName = $name;
    }

    /**
     * session_id设置
     * @access public
     * @param  string     $id session_id
     * @param  int        $expire Session有效期
     * @return void
     */
    public static function setId($id, $expire = null)
    {
        Cookie::set(self::$sessionName, $id, $expire);
    }

    /**
     * 获取session_id
     * @access public
     * @param  bool        $regenerate 不存在是否自动生成
     * @return string
     */
    public static function getId($regenerate = true)
    {
        $sessionId = Cookie::get(self::$sessionName) ?: '';

        if (!$sessionId && $regenerate) {
            $sessionId = self::regenerate();
        }

        return $sessionId;
    }

    /**
     * session设置
     * @access public
     * @param  string        $name session名称
     * @param  mixed         $value session值
     * @return void
     */
    public static function set($name, $value = '', $prefix = null)
    {
        empty(self::$init) && self::boot();

        $sessionId = self::getId();

        self::setSession($sessionId, $name, $value);
    }

    /**
     * session设置
     * @access protected
     * @param  string        $sessionId session_id
     * @param  string        $name session名称
     * @param  mixed         $value session值
     * @param  string|null   $prefix 作用域（前缀）
     * @return void
     */
    protected static function setSession($sessionId, $name, $value)
    {
        if (strpos($name, '.')) {
            // 二维数组赋值
            list($name1, $name2) = explode('.', $name);

            self::$data[$sessionId][$name1][$name2] = $value;
        } else {
            self::$data[$sessionId][$name] = $value;
        }

        // 持久化session数据
        self::writeSessionData($sessionId);
    }

    /**
     * session获取
     * @access public
     * @param  string        $name session名称
     * @return mixed
     */
    public static function get($name = '', $prefix = null)
    {
        empty(self::$init) && self::boot();

        $sessionId = self::getId();

        return self::readSession($sessionId, $name);
    }

    /**
     * session获取
     * @access protected
     * @param  string        $sessionId session_id
     * @param  string        $name session名称
     * @return mixed
     */
    protected static function readSession($sessionId, $name = '')
    {
        $value = isset(self::$data[$sessionId]) ? self::$data[$sessionId] : [];

        if (!is_array($value)) {
            $value = [];
        }

        if ('' != $name) {
            $name = explode('.', $name);

            foreach ($name as $val) {
                if (isset($value[$val])) {
                    $value = $value[$val];
                } else {
                    $value = null;
                    break;
                }
            }
        }

        return $value;
    }

    /**
     * 删除session数据
     * @access public
     * @param  string|array  $name session名称
     * @return void
     */
    public static function delete($name, $prefix = null)
    {
        empty(self::$init) && self::boot();

        $sessionId = self::getId(false);

        if ($sessionId) {
            self::deleteSession($sessionId, $name);

            // 持久化session数据
            self::writeSessionData($sessionId);
        }
    }

    /**
     * 删除session数据
     * @access protected
     * @param  string        $sessionId session_id
     * @param  string|array  $name session名称
     * @return void
     */
    protected static function deleteSession($sessionId, $name)
    {
        if (is_array($name)) {
            foreach ($name as $key) {
                self::deleteSession($sessionId, $key);
            }
        } elseif (strpos($name, '.')) {
            list($name1, $name2) = explode('.', $name);
            unset(self::$data[$sessionId][$name1][$name2]);
        } else {
            unset(self::$data[$sessionId][$name]);
        }
    }

    protected static function writeSessionData($sessionId)
    {
        if (self::$swooleTable) {
            self::$swooleTable->set('sess_' . $sessionId, [
                'data'   => json_encode(self::$data[$sessionId]),
                'expire' => time() + self::$expire,
            ]);
        } else {
            Cache::set('sess_' . $sessionId, self::$data[$sessionId], self::$expire);
        }
    }

    /**
     * 清空session数据
     * @access public
     * @return void
     */
    public static function clear($prefix = null)
    {
        empty(self::$init) && self::boot();

        $sessionId = self::getId(false);

        if ($sessionId) {
            self::clearSession($sessionId);
        }
    }

    /**
     * 清空session数据
     * @access protected
     * @param  string        $sessionId session_id
     * @return void
     */
    protected static function clearSession($sessionId)
    {
        self::$data[$sessionId] = [];

        if (self::$swooleTable) {
            self::$swooleTable->del('sess_' . $sessionId);
        } else {
            Cache::rm('sess_' . $sessionId);
        }
    }

    /**
     * 判断session数据
     * @access public
     * @param  string        $name session名称
     * @return bool
     */
    public static function has($name, $prefix = null)
    {
        empty(self::$init) && self::boot();

        $sessionId = self::getId(false);

        if ($sessionId) {
            return self::hasSession($sessionId, $name);
        }

        return false;
    }

    /**
     * 判断session数据
     * @access protected
     * @param  string        $sessionId session_id
     * @param  string        $name session名称
     * @return bool
     */
    protected static function hasSession($sessionId, $name)
    {
        $value = isset(self::$data[$sessionId]) ? self::$data[$sessionId] : [];

        $name = explode('.', $name);

        foreach ($name as $val) {
            if (!isset($value[$val])) {
                return false;
            } else {
                $value = $value[$val];
            }
        }

        return true;
    }

    /**
     * 启动session
     * @access public
     * @return void
     */
    public static function start()
    {
        $sessionId = self::getId();

        // 读取缓存数据
        if (empty(self::$data[$sessionId])) {
            if (!empty(self::$config['use_swoole_table'])) {
                self::$swooleTable = Container::get('swoole_table');

                $result = self::$swooleTable->get('sess_' . $sessionId);

                if (0 == $result['expire'] || time() <= $result['expire']) {
                    $data = $result['data'];
                }
            } else {
                $data = Cache::get('sess_' . $sessionId);
            }

            if (!empty($data)) {
                self::$data[$sessionId] = $data;
            }
        }

        self::$init = true;
    }

    /**
     * 销毁session
     * @access public
     * @return void
     */
    public static function destroy()
    {
        $sessionId = self::getId(false);

        if ($sessionId) {
            self::destroySession($sessionId);
        }

        self::$init = null;
    }

    /**
     * 销毁session
     * @access protected
     * @param  string        $sessionId session_id
     * @return void
     */
    protected static function destroySession($sessionId)
    {
        if (isset(self::$data[$sessionId])) {
            unset(self::$data[$sessionId]);

            if (self::$swooleTable) {
                self::$swooleTable->del('sess_' . $sessionId);
            } else {
                Cache::rm('sess_' . $sessionId);
            }
        }
    }

    /**
     * 生成session_id
     * @access public
     * @param  bool $delete 是否删除关联会话文件
     * @return string
     */
    public static function regenerate($delete = false)
    {
        if ($delete) {
            self::destroy();
        }

        $sessionId = md5(microtime(true) . uniqid());

        self::setId($sessionId);

        return $sessionId;
    }

    /**
     * 暂停session
     * @access public
     * @return void
     */
    public static function pause()
    {
        self::$init = false;
    }
}
