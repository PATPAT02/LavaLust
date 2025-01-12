<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
/**
 * ------------------------------------------------------------------
 * LavaLust - an opensource lightweight PHP MVC Framework
 * ------------------------------------------------------------------
 *
 * MIT License
 * 
 * Copyright (c) 2020 Ronald M. Marasigan
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @package LavaLust
 * @author Ronald M. Marasigan <ronald.marasigan@yahoo.com>
 * @copyright Copyright 2020 (https://ronmarasigan.github.io)
 * @since Version 1
 * @link https://lavalust.pinoywap.org
 * @license https://opensource.org/licenses/MIT MIT License
 */

/*
* ------------------------------------------------------
*  Class Database / Model
* ------------------------------------------------------
*/
class Database {
    private static $instance = NULL;
    private $db = NULL;
    private $table;
    private $columns;
    private $sql;
    private $bindValues;
    private $getSQL;
    private $join = NULL;
    private $where;
    private $grouped = false;
    private $whereCount = 0;
    private $rowCount = 0;
    private $limit;
    private $orderBy;
    private $groupBy = NULL;
    private $having = NULL;
    private $lastIDInserted = 0;
    private $transactionCount = 0;
    private $operators = array('=', '!=', '<', '>', '<=', '>=', '<>');


    public function __construct()
    {
        $database_config = database_config();
        $this->driver = $database_config['driver'];
        $this->charset = $database_config['charset'];
        $this->dbost = $database_config['hostname'];
        $this->port = $database_config['port'];
        $this->dbname = $database_config['database'];
        $this->dbuser = $database_config['username'];
        $this->dbpass = $database_config['password'];
        $this->dsn = ''.$this->driver.':host=' . $this->dbost . ';dbname=' . $this->dbname . ';charset=' . $this->charset . ';port=' . $this->port;

        $options = array(
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        );

        try {
            $this->db = new PDO($this->dsn, $this->dbuser, $this->dbpass, $options);
            $database_config = NULL;
        } catch (Exception $e) {
            show_error('Database Error Occured', $e->getMessage(), 'error_db', 500);
        }
    }

    /**
     * Get Database Instance
     * 
     * @return instance
     */
    public static function instance()
    {
        if (!self::$instance) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    /**
     * Raw Query
     * 
     * @param  string $query
     * @param  array  $args  arguments
     * @return result
     */
    public function raw($query, $args = [])
    {
        $this->resetQuery();
        $query = trim($query);
        $this->getSQL = $query;
        $this->bindValues = $args;

        if (strpos( strtoupper($query), "SELECT" ) === 0 ) {
            $stmt = $this->db->prepare($query);
            $stmt->execute($this->bindValues);
            $this->rowCount = $stmt->rowCount();
            return $stmt->fetchAll();
        }else{
            $this->getSQL = $query;
            $stmt = $this->db->prepare($query);
            $stmt->execute($this->bindValues);
            return $stmt->rowCount();
        }
    }

    /**
     * Execute insert, update and delete
     * 
     * @return query
     */
    public function exec()
    {
            $this->sql .= $this->where;
            $this->getSQL = $this->sql;
            $stmt = $this->db->prepare($this->sql);
            $stmt->execute($this->bindValues);
            if (strpos( strtoupper($this->sql), "INSERT" ) === 0 ) {
                $this->lastIDInserted = $this->db->lastInsertId();
                return $this->lastIDInserted;
            }
            else
                return $stmt->rowCount();
    }

    /**
     * Reset queries
     * 
     * @return $this
     */
    private function resetQuery()
    {
        $this->table = NULL;
        $this->columns = NULL;
        $this->sql = NULL;
        $this->bindValues = NULL;
        $this->limit = NULL;
        $this->orderBy = NULL;
        $this->groupBy = NULL;
        $this->having = NULL;
        $this->getSQL = NULL;
        $this->where = NULL;
        $this->join = NULL;
        $this->rowCount = 0;
        $this->lastIDInserted = 0;
    }

    /**
     * Delete Records
     * 
     * @return $this
     */
    public function delete()
    {
        $this->sql = "DELETE FROM {$this->table}";
        
        $this->exec();
    }

    public function update($fields = [])
    {
        $set = '';
        $values = [];

        foreach ($fields as $column => $field) {
            $values[] = $column . ' = ?';
            $this->bindValues[] = $field;
        }
        $set .= implode(', ', $values);

        $this->sql = "UPDATE {$this->table} SET {$set}";

        $this->exec();
    }


    /**
     * Insert record
     * 
     * @param  array  $fields
     * @return $this
     */
    public function insert($fields = [])
    {
        $keys = implode(', ', array_keys($fields));
        $values = '';
        $x = 1;
        foreach ($fields as $field => $value) {
            $values .='?';
            $this->bindValues[] =  $value;
            if ($x < count($fields)) {
                $values .=', ';
            }
            $x++;
        }
 
        $this->sql = "INSERT INTO {$this->table} ({$keys}) VALUES ({$values})";
        
        $this->exec();
    }

    /**
     * Last inserted ID
     * 
     * @return $this
     */
    public function last_id()
    {
        return $this->lastIDInserted;
    }

    /**
     * Get table names
     * 
     * @param  string $table_name
     * @return $this
     */
    public function table($table_name)
    {
        $this->resetQuery();
        $this->table = $table_name;
        return $this;
    }

    /**
     * Select
     * 
     * @param  string $columns
     * @return $this
     */
    public function select($columns)
    {
        $columns = explode(',', $columns);
        foreach ($columns as $key => $column) {
            $columns[$key] = trim($column);
        }
        
        $columns = implode(', ', $columns);

        $this->columns = "{$columns}";
        return $this;
    }

    /**
     * max_min_sum_count_avg
     * 
     * @param  string $column
     * @param  string $alias
     * @param  string $type
     * @return $this
     */
    public function _max_min_sum_count_avg($column, $alias = null, $type = 'MAX')
    {
        if( ! in_array($type, array('MAX', 'MIN', 'SUM', 'COUNT', 'AVG'))) {
            show_error('Database Error Occured', 'Invalid function type: ' . html_escape($type), 'error_db', 500);
        }

        $function = $type . '(' . $column . ')' . (! is_null($alias) ? ' AS ' . $alias : '');
        $this->columns = ( is_null($this->columns) ? $function : $this->columns . ', ' . $function);
 
        return $this;
    }

    /**
     * select_max
     * 
     * @param  string $column
     * @param  string $alias
     * @return $this 
     */
    public function select_max($column, $alias = null)
    {
        return $this->_max_min_sum_count_avg($column, $alias, $type = 'MAX');
    }

    /**
     * select_min
     * 
     * @param  string $column
     * @param  string $alias
     * @return $this 
     */
    public function select_min($column, $alias = null)
    {
        return $this->_max_min_sum_count_avg($column, $alias, $type = 'MIN');
    }

    /**
     * select_sum
     * 
     * @param  string $column
     * @param  string $alias
     * @return $this
     */
    public function select_sum($column, $alias = null)
    {
        return $this->_max_min_sum_count_avg($column, $alias, $type = 'SUM');
    }

    /**
     * select_count
     * 
     * @param  string $column
     * @param  string $alias
     * @return $this 
     */
    public function select_count($column, $alias = null)
    {
        return $this->_max_min_sum_count_avg($column, $alias, $type = 'COUNT');
    }

    /**
     * select_avg
     * 
     * @param  string $column
     * @param  string $alias
     * @return $this 
     */
    public function select_avg($column, $alias = null)
    {
        return $this->_max_min_sum_count_avg($column, $alias, $type = 'AVG');
    }

    /**
     * join
     * 
     * @param  string $table_name
     * @param  string $cond
     * @param  string $type
     * @return $this
     */
    public function join($table_name, $cond, $type = '')
    {
        //Planning to add but im worrying about the loading speed.
        /*
        $flag = false;
        foreach ($this->$operators as $operator) {
            if (strpos($cond, $operator) !== FALSE) {
                $flag = true;
            } else {
                $flag = false;
            }
        }
        */
       
        $this->join = (is_null($this->join))
            ? ' ' . $type . 'JOIN' . ' ' . $table_name . ' ON ' . $cond
            : $this->join . ' ' . $type . 'JOIN' . ' ' . $table_name . ' ON ' . $cond;

        return $this;
    }

    /**
     * inner_join
     * 
     * @param  string $table_name
     * @param  string $cond
     * @return $this
     */
    public function inner_join($table_name, $cond)
    {
        return $this->join($table_name, $cond, 'INNER ');
    }

    /**
     * left_join
     * 
     * @param  string $table_name
     * @param  string $cond
     * @return $this
     */
    public function left_join($table_name, $cond)
    {
        $this->join($table_name, $cond, 'LEFT ');

        return $this;
    }

    /**
     * right_join
     * 
     * @param  string $table_name
     * @param  string $cond
     * @return $this
     */
    public function right_join($table_name, $cond)
    {
        $this->join($table_name, $cond, 'RIGHT ');

        return $this;
    }

    /**
     * full_outer_join
     * 
     * @param  string $table_name
     * @param  string $cond
     * @return $this
     */
    public function full_outer_join($table_name, $cond)
    {
        $this->join($table_name, $cond, 'FULL OUTER ');

        return $this;
    }

    /**
     * left_outer_join
     * 
     * @param  string $table_name
     * @param  string $cond
     * @return $this
     */
    public function left_outer_join($table_name, $cond)
    {
        $this->join($table_name, $cond, 'LEFT OUTER ');

        return $this;
    }

    /**
     * right_outer_join
     * 
     * @param  string $table_name
     * @param  string $cond
     * @return $this
     */
    public function right_outer_join($table_name, $cond)
    {
        $this->join($table_name, $cond, 'RIGHT OUTER ');

        return $this;
    }

    /**
     * grouped
     * 
     * @param  Closure $obj
     * @return $this
     */
    public function grouped(Closure $obj)
    {
        $this->grouped = true;
        call_user_func_array($obj, [$this]);
        $this->where .= ')';

        return $this;
    }

    /**
     * where
     * 
     * @param  string $where
     * @param  string $op
     * @param  mixed $val
     * @param  string $type
     * @param  string $andOr
     * @return $this
     */
    public function where($where, $op = null, $val = null, $type = '', $andOr = 'AND')
    {
        if (is_array($where) && ! empty($where)) {
            $_where = [];
            foreach ($where as $column => $data) {
                $_where[] = $type . $column . ' = ?';
                $this->bindValues[] = $data;
            }
            $where = implode(' ' . $andOr . ' ', $_where);
        } else {
            if (is_null($where) || empty($where)) {
                return $this;
            }

            if (is_array($op)) {
                $params = explode('?', $where);
                $_where = '';
                foreach ($params as $key => $value) {
                    if (! empty($value)) {
                        $_where .= $type . $value . (isset($op[$key]) ? ' ? ' : '');
                        $this->bindValues[] = $op[$key];
                    }
                }
                $where = $_where;
            } elseif (! in_array($op, $this->operators) || $op == false) {
                $where = $type . $where . ' = ?';
                $this->bindValues[] = $op;
            } else {
                $where = $type . $where . ' ' . $op . ' ?';
                $this->bindValues[] = $val;
            }
        }

        if ($this->grouped) {
            $where = '(' . $where;
            $this->grouped = false;
        }

        $this->where = (is_null($this->where))
            ? ' WHERE ' . $where
            : $this->where . ' ' . $andOr . ' ' . $where;

        return $this;
    }

    /**
     * or_where
     * 
     * @param  string $where
     * @param  string $op
     * @param  mixed $val
     * @return $this
     */
    public function or_where($where, $op = null, $val = null)
    {
        $this->where($where, $op, $val, '', 'OR');

        return $this;
    }

    /**
     * not_where
     * 
     * @param  string $where
     * @param  string $op
     * @param  mixed $val
     * @return $this
     */
    public function not_where($where, $op = null, $val = null)
    {
        $this->where($where, $op, $val, 'NOT ', 'AND');

        return $this;
    }

    /**
     * or_not_where
     * 
     * @param  string $where
     * @param  string $op
     * @param  mixed $val
     * @return $this
     */
    public function or_not_where($where, $op = null, $val = null)
    {
        $this->where($where, $op, $val, 'NOT ', 'OR');

        return $this;
    }

    /**
     * where_null
     * 
     * @param  string $where
     * @return $this
     */
    public function where_null($where)
    {
        $where = $where . ' IS NULL';
        $this->where = (is_null($this->where))
            ? ' WHERE ' . $where
            : $this->where . ' ' . 'AND ' . $where;

        return $this;
    }

    /**
     * where_not_null
     * 
     * @param  string $where
     * @return $this
     */
    public function where_not_null($where)
    {
        $where = $where . ' IS NOT NULL';
        $this->where = (is_null($this->where))
            ? ' WHERE ' . $where
            : $this->where . ' ' . 'AND ' . $where;

        return $this;
    }

    /**
     * like
     * 
     * @param  string $field
     * @param  mixed $data
     * @param  string $type
     * @param  string $andOr
     * @return $this
     */
    public function like($field, $data, $type = '', $andOr = 'AND')
    {
        $this->bindValues[] = $data;
        $where = $field . ' ' . $type . 'LIKE ?';

        if ($this->grouped) {
            $where = '(' . $where;
            $this->grouped = false;
        }

        $this->where = (is_null($this->where))
            ? ' WHERE ' . $where
            : $this->where . ' ' . $andOr . ' ' . $where;

        return $this;
    }

    /**
     * or_like
     * @param  string $field
     * @param  mixed $data
     * @return $this
     */
    public function or_like($field, $data)
    {
        return $this->like($field, $data, '', 'OR');
    }

    /**
     * not_like
     * @param  string $field
     * @param  mixed $data
     * @return $this
     */
    public function not_like($field, $data)
    {
        return $this->like($field, $data, 'NOT ', 'AND');
    }

    /**
     * or_not_like
     * 
     * @param  string $field
     * @param  mixed $data
     * @return $this
     */
    public function or_not_like($field, $data)
    {
        return $this->like($field, $data, 'NOT ', 'OR');
    }

    /**
     * between
     * 
     * @param  string $field
     * @param  mixed $value1
     * @param  mixed $value2
     * @param  string $type
     * @param  string $andOr
     * @return $this
     */
    public function between($field, $value1, $value2, $type = '', $andOr = 'AND')
    {
        $this->bindValues[] = $value1;
        $this->bindValues[] = $value2;
        $where = '(' . $field . ' ' . $type . 'BETWEEN ?  AND ?)';

        if ($this->grouped) {
            $where = '(' . $where;
            $this->grouped = false;
        }

        $this->where = (is_null($this->where))
            ? ' WHERE ' . $where
            : $this->where . ' ' . $andOr . ' ' . $where;

        return $this;
    }

    /**
     * not_between
     * 
     * @param  string $field
     * @param  mixed $value1
     * @param  mixed $value2
     * @return $this
     */
    public function not_between($field, $value1, $value2)
    {
        return $this->between($field, $value1, $value2, 'NOT ', 'AND');
    }

    /**
     * or_between
     * 
     * @param  string $field
     * @param  mixed $value1
     * @param  mixed $value2
     * @return $this
     */
    public function or_between($field, $value1, $value2)
    {
        return $this->between($field, $value1, $value2, '', 'OR');
    }

    /**
     * or_not_between
     * 
     * @param  string $field
     * @param  mixed $value1
     * @param  mixed $value2
     * @return $this
     */
    public function or_not_between($field, $value1, $value2)
    {
        return $this->between($field, $value1, $value2, 'NOT ', 'OR');
    }

    /**
     * in
     * 
     * @param  string $field
     * @param  array  $keys
     * @param  string $type
     * @param  string $andOr
     * @return $this
     */
    public function in($field, array $keys, $type = '', $andOr = 'AND')
    {
        if (is_array($keys)) {
            $_keys = [];
            foreach ($keys as $k => $v) {
                $_keys[] = (is_numeric($v) ? $v : '?');
            }
            $where = $field . ' ' . $type . 'IN (' . implode(', ', $_keys) . ')';

            if ($this->grouped) {
                $where = '(' . $where;
                $this->grouped = false;
            }

            $this->where = (is_null($this->where))
                ? ' WHERE ' . $where
                : $this->where . ' ' . $andOr . ' ' . $where;
        }

        return $this;
    }

    /**
     * not_in
     * 
     * @param  string $field
     * @param  array  $keys
     * @return $this
     */
    public function not_in($field, array $keys)
    {
        $this->in($field, $keys, 'NOT ', 'AND');

        return $this;
    }

    /**
     * or_in
     * 
     * @param  string $field
     * @param  array  $keys
     * @return $this
     */
    public function or_in($field, array $keys)
    {
        $this->in($field, $keys, '', 'OR');

        return $this;
    }

    /**
     * or_not_in
     * 
     * @param  string $field
     * @param  array  $keys
     * @return $this
     */
    public function or_not_in($field, array $keys)
    {
        $this->in($field, $keys, 'NOT ', 'OR');

        return $this;
    }

    /**
     * limit
     * 
     * @param  integer $limit
     * @param  integer $offset
     * @return $this
     */
    public function limit($limit, $offset=NULL)
    {
        if ($offset ==NULL ) {
            $this->limit = " LIMIT {$limit}";
        }else{
            $this->limit = " LIMIT {$limit} OFFSET {$offset}";
        }

        return $this;
    }

    /**
     * order_by
     * 
     * @param  string $field_name
     * @param  string $order
     * @return $this
     */
    public function order_by($field_name, $order = 'ASC')
    {
        $field_name = trim($field_name);

        $order =  trim(strtoupper($order));

        if ($field_name !== NULL && ($order == 'ASC' || $order == 'DESC')) {
            if ($this->orderBy ==NULL ) {
                $this->orderBy = " ORDER BY {$field_name} {$order}";
            }else{
                $this->orderBy .= ", {$field_name} {$order}";
            }
            
        }

        return $this;
    }

    /**
     * group_by
     * 
     * @param  string $groupBy
     * @return $this
     */
     public function group_by($groupBy)
    {
        $this->groupBy = ' GROUP BY ';
        $this->groupBy .= (is_array($groupBy))
            ? implode(', ', $groupBy)
            : $groupBy;

        return $this;
    }

    /**
     * having
     * 
     * @param  string $field
     * @param  string $op
     * @param  mixed $val
     * @return $this
     */
    public function having($field, $op = null, $val = null)
    {
        $this->having = ' HAVING ';
        if (is_array($op)) {
            $fields = explode('?', $field);
            $where = '';
            foreach ($fields as $key => $value) {
                if (! empty($value)) {
                    $where .= $value . (isset($op[$key]) ? ' ? ' : '');
                    $this->bindValues[] = $op[$key];
                }
            }
            $this->having .= $where;
        } elseif (! in_array($op, $this->operators)) {
            $this->having .= $field . ' > ' . ' ? ';
            $this->bindValues[] = $op;
        } else {
            $this->having .= $field . ' ' . $op . ' ' . ' ? ';
            $this->bindValues[] = $val;
        }

        return $this;
    }

    /**
     * buildQuery
     * 
     * @return $this
     */
    private function buildQuery()
    {
        if ( $this->columns !== NULL ) {
            $select = $this->columns;
        }else{
            $select = "*";
        }

        $this->sql = "SELECT $select FROM $this->table";
        if ($this->join !== NULL) {
            $this->sql .= $this->join;
        }

        if ($this->where !== NULL) {
            $this->sql .= $this->where;
        }

        if ($this->groupBy !== NULL) {
            $this->sql .= $this->groupBy;
        }

        if ($this->having !== NULL) {
            $this->sql .= $this->having;
        }

        if ($this->orderBy !== NULL) {
            $this->sql .= $this->orderBy;
        }

        if ($this->limit !== NULL) {
            $this->sql .= $this->limit;
        }
    }

    /**
     * get
     * 
     * @param  string $mode
     * @return result
     */
    public function get($mode = PDO::FETCH_ASSOC)
    {
        $this->buildQuery();
        $this->getSQL = $this->sql;
        try {
            $stmt = $this->db->prepare($this->sql);
            $stmt->execute($this->bindValues);
            $this->rowCount = $stmt->rowCount();
            return $stmt->fetch($mode);
        } catch(PDOException $e) {
            show_error('Database Error Occured', $e->getMessage().'<br>SQL Query: '.html_escape($this->getSQL), 'error_db', 500);
        }
    }

    /**
     * get_all
     * 
     * @return result
     */
    public function get_all()
    {
        $this->buildQuery();
        $this->getSQL = $this->sql;
        try {
            $stmt = $this->db->prepare($this->sql);
            $stmt->execute($this->bindValues);
            $this->rowCount = $stmt->rowCount();
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            show_error('Database Error Occured', $e->getMessage().'<br>SQL Query: '.html_escape($this->getSQL), 'error_db', 500);
        }
    }

    /**
     * get_sql
     * 
     * @return string
     */
    public function get_sql()
    {
        return $this->getSQL;
    }

    /**
     * row_count
     * 
     * @return integer
     */
    public function row_count()
    {
        return $this->rowCount;
    }

    /**
     * transaction
     * 
     * @return result
     */
    public function transaction()
    {
        if (! $this->transactionCount++) {
            return $this->db->beginTransaction();
        }

        $this->pdo->exec('SAVEPOINT trans' . $this->transactionCount);
        return $this->transactionCount >= 0;
    }

    /**
     * commit
     * 
     * @return result
     */
    public function commit()
    {
        if (! --$this->transactionCount) {
            return $this->db->commit();
        }

        return $this->transactionCount >= 0;
    }

    /**
     * roll_back
     * 
     * @return result
     */
    public function roll_back()
    {
        if (--$this->transactionCount) {
            $this->db->exec('ROLLBACK TO trans' . ($this->transactionCount + 1));
            return true;
        }

        return $this->db->rollBack();
    }

    /**
     * __destruct
     */
    public function __destruct()
    {
        $this->db = null;
    }
}