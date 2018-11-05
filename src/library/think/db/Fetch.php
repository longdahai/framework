<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace think\db;

use think\Exception;

class Fetch
{
    /**
     * 查询对象
     * @var Query
     */
    protected $query;

    /**
     * Connection对象
     * @var Connection
     */
    protected $connection;

    /**
     * Builder对象
     * @var Builder
     */
    protected $builder;

    /**
     * 创建一个查询SQL获取对象
     *
     * @param  Query    $query      查询对象
     */
    public function __construct(Query $query)
    {
        $this->query      = $query;
        $this->connection = $query->getConnection();
        $this->builder    = $this->connection->getBuilder();
    }

    /**
     * 聚合查询
     * @access public
     * @param  string $aggregate    聚合方法
     * @param  string $field        字段名
     * @return string
     */
    public function aggregate(string $aggregate, string $field): string
    {
        $options = $this->query->parseOptions();

        $field = $aggregate . '(' . $this->builder->parseKey($this->query, $field) . ') AS tp_' . strtolower($aggregate);

        if (isset($options['field'])) {
            $this->query->removeOption('field');
        }

        if (is_string($field)) {
            $field = array_map('trim', explode(',', $field));
        }

        $this->query->setOption('field', $field);
        $this->query->setOption('limit', '1');

        // 生成查询SQL
        $sql = $this->builder->select($this->query);

        if (isset($options['field'])) {
            $this->query->setOption('field', $options['field']);
        } else {
            $this->query->removeOption('field');
        }

        // 查询完成后清空聚合字段信息
        $this->query->removeOption('limit');
        $this->query->removeOption('field');

        return $this->fetch($sql);
    }

    /**
     * 得到某个字段的值
     * @access public
     * @param  string $field   字段名
     * @return string
     */
    public function value(string $field): string
    {
        $options = $this->query->parseOptions();

        if (isset($options['field'])) {
            $this->query->removeOption('field');
        }

        if (is_string($field)) {
            $field = array_map('trim', explode(',', $field));
        }

        $this->query->setOption('field', $field);
        $this->query->setOption('limit', '1');

        // 生成查询SQL
        $sql = $this->builder->select($this->query);

        if (isset($options['field'])) {
            $this->query->setOption('field', $options['field']);
        } else {
            $this->query->removeOption('field');
        }

        $this->query->removeOption('limit');

        return $this->fetch($sql);
    }

    /**
     * 得到某个列的数组
     * @access public
     * @param  string $field 字段名 多个字段用逗号分隔
     * @param  string $key   索引
     * @return string
     */
    public function column(string $field, string $key = ''): string
    {
        $options = $this->query->parseOptions();

        if (isset($options['field'])) {
            $this->query->removeOption('field');
        }

        if ($key && '*' != $field) {
            $field = $key . ',' . $field;
        }

        $field = array_map('trim', explode(',', $field));

        $this->query->setOption('field', $field);

        // 生成查询SQL
        $sql = $this->builder->select($this->query);

        if (isset($options['field'])) {
            $this->query->setOption('field', $options['field']);
        } else {
            $this->query->removeOption('field');
        }

        return $this->fetch($sql);
    }

    /**
     * 插入记录
     * @access public
     * @param  array   $data         数据
     * @param  boolean $replace      是否replace
     * @return string
     */
    public function insert(array $data = [], bool $replace = false): string
    {
        $options = $this->query->parseOptions();

        $this->query->setOption('data', array_merge($options['data'], $data));

        $sql = $this->builder->insert($this->query, $replace);
        return $this->fetch($sql);
    }

    /**
     * 插入记录并获取自增ID
     * @access public
     * @param  array   $data     数据
     * @param  boolean $replace  是否replace
     * @return string
     */
    public function insertGetId(array $data, bool $replace = false)
    {
        return $this->insert($data, $replace);
    }

    /**
     * 批量插入记录
     * @access public
     * @param  array     $dataSet 数据集
     * @param  boolean   $replace 是否replace
     * @param  integer   $limit   每次写入数据限制
     * @return string
     */
    public function insertAll(array $dataSet = [], bool $replace = false, int $limit = null): string
    {
        $options = $this->query->parseOptions();

        if (empty($dataSet)) {
            $dataSet = $options['data'];
        }

        if (empty($limit) && !empty($options['limit'])) {
            $limit = $options['limit'];
        }

        if ($limit) {
            $array = array_chunk($dataSet, $limit, true);

            foreach ($array as $item) {
                $sql  = $this->builder->insertAll($this->query, $item, $replace);
                $bind = $this->query->getBind();

                $fetchSql[] = $this->getRealSql($sql, $bind);
            }

            return implode(';', $fetchSql);
        }

        $sql = $this->builder->insertAll($this->query, $dataSet, $replace);
        return $this->fetch($sql);
    }

    /**
     * 通过Select方式插入记录
     * @access public
     * @param  array    $fields 要插入的数据表字段名
     * @param  string   $table  要插入的数据表名
     * @return string
     */
    public function selectInsert(array $fields, string $table): string
    {
        $this->query->parseOptions();
        $sql = $this->builder->selectInsert($this->query, $fields, $table);
        return $this->fetch($sql);
    }

    /**
     * 更新记录
     * @access public
     * @param  mixed $data 数据
     * @return string
     */
    public function update(array $data = []): string
    {
        $options = $this->query->parseOptions();

        $this->query->setOption('data', array_merge($options['data'], $data));

        if (isset($options['cache']) && is_string($options['cache']['key'])) {
            $key = $options['cache']['key'];
        }

        $pk            = $this->query->getPk($options);
        $data          = $options['data'];
        $options['pk'] = $pk;

        if (empty($options['where'])) {
            // 如果存在主键数据 则自动作为更新条件
            if (is_string($pk) && isset($data[$pk])) {
                $where[$pk] = [$pk, '=', $data[$pk]];
                unset($data[$pk]);
            } elseif (is_array($pk)) {
                // 增加复合主键支持
                foreach ($pk as $field) {
                    if (isset($data[$field])) {
                        $where[$field] = [$field, '=', $data[$field]];
                    } else {
                        // 如果缺少复合主键数据则不执行
                        throw new Exception('miss complex primary data');
                    }
                    unset($data[$field]);
                }
            }

            if (!isset($where)) {
                // 如果没有任何更新条件则不执行
                throw new Exception('miss update condition');
            } else {
                $options['where']['AND'] = $where;
                $this->query->setOption('where', ['AND' => $where]);
            }
        }

        // 更新数据
        $this->query->setOption('data', $data);

        // 生成UPDATE SQL语句
        $sql = $this->builder->update($this->query);
        return $this->fetch($sql);
    }

    /**
     * 删除记录
     * @access public
     * @param  mixed $data 表达式 true 表示强制删除
     * @return string
     */
    public function delete($data = null): string
    {
        $options = $this->query->parseOptions();

        if (!is_null($data) && true !== $data) {
            // AR模式分析主键条件
            $this->query->parsePkWhere($data);
        }

        if (!empty($options['soft_delete'])) {
            // 软删除
            list($field, $condition) = $options['soft_delete'];
            if ($condition) {
                $this->query->setOption('soft_delete', null);
                $this->query->setOption('data', [$field => $condition]);
                // 生成删除SQL语句
                $sql = $this->builder->delete($this->query);
                return $this->fetch($sql);
            }
        }

        $this->query->setOption('data', $data);
        // 生成删除SQL语句
        $sql = $this->builder->delete($this->query);
        return $this->fetch($sql);
    }

    /**
     * 查找记录 返回SQL
     * @access public
     * @param  mixed $data
     * @return string
     */
    public function select($data = null): string
    {
        if ($data instanceof \Closure) {
            $data($this);
            $data = null;
        }

        $this->query->parseOptions();

        if (!is_null($data)) {
            // 主键条件分析
            $this->query->parsePkWhere($data);
        }

        $this->query->setOption('data', $data);
        // 生成查询SQL
        $sql = $this->builder->select($this->query);
        return $this->fetch($sql);
    }

    /**
     * 查找单条记录 返回SQL语句
     * @access public
     * @param  mixed $data
     * @return string
     */
    public function find($data = null): string
    {
        if ($data instanceof \Closure) {
            $data($this);
            $data = null;
        }

        $this->query->parseOptions();

        if (!is_null($data)) {
            // AR模式分析主键条件
            $this->query->parsePkWhere($data);
        }

        $this->query->setOption('data', $data);

        $this->query->setOption('limit', '1');

        // 生成查询SQL
        $sql = $this->builder->select($this->query);

        $this->query->removeOption('limit');

        // 获取实际执行的SQL语句
        return $this->fetch($sql);
    }

    /**
     * 查找多条记录 如果不存在则抛出异常
     * @access public
     * @param  array|string|Query|\Closure $data
     * @return string
     */
    public function selectOrFail($data = null): string
    {
        return $this->failException(true)->select($data);
    }

    /**
     * 查找单条记录 如果不存在则抛出异常
     * @access public
     * @param  array|string|Query|\Closure $data
     * @return string
     */
    public function findOrFail($data = null): string
    {
        return $this->failException(true)->find($data);
    }

    /**
     * 查找单条记录 如果不存在则返回空
     * @access public
     * @param  array|string|Query|\Closure $data
     * @return string
     */
    public function findOrEmpty($data = null): string
    {
        return $this->allowEmpty(true)->find($data);
    }

    /**
     * 获取实际的SQL语句
     * @access public
     * @param  string $sql
     * @return string
     */
    public function fetch(string $sql): string
    {
        $bind = $this->query->getBind();

        return $this->connection->getRealSql($sql, $bind);
    }

    /**
     * COUNT查询
     * @access public
     * @param  string $field 字段名
     * @return string
     */
    public function count(string $field = '*'): string
    {
        $options = $this->query->parseOptions();

        if (!empty($options['group'])) {
            // 支持GROUP
            $bind   = $this->query->getBind();
            $subSql = $this->query->options($options)->field('count(' . $field . ') AS think_count')->bind($bind)->buildSql();

            $query = $this->query->newQuery()->table([$subSql => '_group_count_']);

            return $query->fetchsql()->aggregate('COUNT', '*');
        } else {
            return $this->aggregate('COUNT', $field);
        }
    }

    /**
     * SUM查询
     * @access public
     * @param  string $field 字段名
     * @return string
     */
    public function sum(string $field): string
    {
        return $this->aggregate('SUM', $field, true);
    }

    /**
     * MIN查询
     * @access public
     * @param  string $field    字段名
     * @return string
     */
    public function min(string $field): string
    {
        return $this->aggregate('MIN', $field);
    }

    /**
     * MAX查询
     * @access public
     * @param  string $field    字段名
     * @return string
     */
    public function max(string $field): string
    {
        return $this->aggregate('MAX', $field);
    }

    /**
     * AVG查询
     * @access public
     * @param  string $field 字段名
     * @return string
     */
    public function avg(string $field): string
    {
        return $this->aggregate('AVG', $field);
    }

    public function __call($method, $args)
    {
        $result = call_user_func_array([$this->query, $method], $args);
        return $result === $this->query ? $this : $result;
    }
}
