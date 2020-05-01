# API

```php
<?php

namespace Dop;

/**
 * Represents a database connection and a factory for SQL fragments.
 *
 * Immutable
 */
class Connection
{

    /**
     * Constructor
     *
     * @param \PDO $pdo
     * @param array $options
     */
    public function __construct(\PDO $pdo, $options = array())
    {
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo = $pdo;

        $defaultIdentDelimiter = $this->driver() === 'mysql' ? '`' : '"';
        $this->identDelimiter = isset($options['identDelimiter']) ?
            $options['identDelimiter'] : $defaultIdentDelimiter;
    }

    /**
     * Returns a basic SELECT query for table $name.
     *
     * @param string $name
     * @return Fragment
     */
    public function query($table)
    {
        return $this('SELECT ::select FROM ::table WHERE ::where ::orderBy ::limit', array(
            'select' => $this('*'),
            'table' => $this->table($table),
            'where' => $this->where(),
            'orderBy' => $this(),
            'limit' => $this()
        ));
    }

    /**
     * Build an insert statement to insert a single row.
     *
     * @param string $table
     * @param array|\Traversable $row
     * @return Fragment
     */
    public function insert($table, $row)
    {
        return $this->insertBatch($table, array($row));
    }

    /**
     * Build single batch statement to insert multiple rows.
     *
     * Create a single statement with multiple value lists.
     * Supports SQL fragment parameters, but not supported by all drivers.
     *
     * @param string $table
     * @param array|\Traversable $rows
     * @return Fragment
     */
    public function insertBatch($table, $rows)
    {
        if ($this->empt($rows)) {
            return $this(self::EMPTY_STATEMENT);
        }
        $columns = $this->columns($rows);

        $lists = array();

        foreach ($rows as $row) {
            $values = array();
            foreach ($columns as $column) {
                if (array_key_exists($column, $row)) {
                    $values[] = $this->value($row[$column]);
                } else {
                    $values[] = 'DEFAULT';
                }
            }
            $lists[] = $this->raw("(" . implode(", ", $values) . ")");
        }

        return $this('INSERT INTO ::table (::columns) VALUES ::values', array(
            'table' => $this->table($table),
            'columns' => $this->ident($columns),
            'values' => $lists
        ));
    }

    /**
     * Insert multiple rows using a prepared statement (directly executed).
     *
     * Prepare a statement and execute it once per row using bound params.
     * Does not support SQL fragments in row data.
     *
     * @param string $table
     * @param array|\Traversable $rows
     * @return Result The prepared result
     */
    public function insertPrepared($table, $rows)
    {
        if ($this->empt($rows)) {
            return $this(self::EMPTY_STATEMENT)->prepare();
        }
        $columns = $this->columns($rows);

        $prepared = $this('INSERT INTO ::table (::columns) VALUES ::values', array(
            'table' => $this->table($table),
            'columns' => $this->ident($columns),
            'values' => $this('(?' . str_repeat(', ?', count($columns) - 1) . ')')
    ))->prepare();

        foreach ($rows as $row) {
            $values = array();

            foreach ($columns as $column) {
                $values[] = (string) $this->format(@$row[$column]);
            }
            $prepared->exec($values);
        }
    }

    /**
     * Build an update statement.
     *
     * UPDATE $table SET $data [WHERE $where]
     *
     * @param string $table
     * @param array|\Traversable $data
     * @param array|string $where
     * @param array|mixed $params
     * @return Fragment
     */
    public function update($table, $data, $where = array(), $params = array())
    {
        if ($this->empt($data)) {
            return $this(self::EMPTY_STATEMENT);
        }

        return $this('UPDATE ::table SET ::set WHERE ::where ::limit', array(
            'table' => $this->table($table),
            'set' => $this->assign($data),
            'where' => $this->where($where, $params),
            'limit' => $this()
    ));
    }

    /**
     * Build a delete statement.
     *
     * DELETE FROM $table [WHERE $where]
     *
     * @param string $table
     * @param array|string $where
     * @param array|mixed $params
     * @return Fragment
     */
    public function delete($table, $where = array(), $params = array())
    {
        return $this('DELETE FROM ::table WHERE ::where ::limit', array(
            'table' => $this->table($table),
            'where' => $this->where($where, $params),
            'limit' => $this()
    ));
    }

    /**
     * Build a conditional expression fragment.
     *
     * @param array|string $condition
     * @param array|mixed $params
     * @param Fragment|null $before
     * @return Fragment
     */
    public function where($condition = null, $params = array(), Fragment $before = null)
    {

    // empty condition evaluates to true
        if (empty($condition)) {
            return $before ? $before : $this('1=1');
        }

        // conditions in key-value array
        if (is_array($condition)) {
            $cond = $before;
            foreach ($condition as $k => $v) {
                $cond = $this->where($k, $v, $cond);
            }
            return $cond;
        }

        // shortcut for basic "column is (in) value"
        if (preg_match('/^[a-z0-9_.`"]+$/i', $condition)) {
            $condition = $this->is($condition, $params);
        } else {
            $condition = $this($condition, $params);
        }

        if ($before && (string) $before !== '1=1') {
            return $this('(??) AND ??', array($before, $condition));
        }

        return $condition;
    }

    /**
     * Build a negated conditional expression fragment.
     *
     * @param string $key
     * @param mixed $value
     * @param Fragment|null $before
     * @return Fragment
     */
    public function whereNot($key, $value = array(), Fragment $before = null)
    {

    // key-value array
        if (is_array($key)) {
            $cond = $before;
            foreach ($key as $k => $v) {
                $cond = $this->whereNot($k, $v, $cond);
            }
            return $cond;
        }

        // "column is not (in) value"
        $condition = $this->isNot($key, $value);

        if ($before && (string) $before !== '1=1') {
            return $this('(??) AND ??', array($before, $condition));
        }

        return $condition;
    }

    /**
     * Build an ORDER BY fragment.
     *
     * @param string $column
     * @param string $direction Must be ASC or DESC
     * @param Fragment|null $before
     * @return Fragment
     */
    public function orderBy($column, $direction = 'ASC', Fragment $before = null)
    {
        if (!preg_match('/^asc|desc$/i', $direction)) {
            throw new Exception('Invalid ORDER BY direction: ' . $direction);
        }

        return $this->raw(
            ($before && (string) $before !== '' ? ($before . ', ') : 'ORDER BY ') .
            $this->ident($column) . ' ' . $direction
        );
    }

    /**
     * Build a LIMIT fragment.
     *
     * @param int $count
     * @param int $offset
     * @return Fragment
     */
    public function limit($count = null, $offset = null)
    {
        if ($count !== null) {
            $count = intval($count);
            if ($count < 1) {
                throw new Exception('Invalid LIMIT count: ' . $count);
            }

            if ($offset !== null) {
                $offset = intval($offset);
                if ($offset < 0) {
                    throw new Exception('Invalid LIMIT offset: ' . $offset);
                }

                return $this->raw('LIMIT ' . $count . ' OFFSET ' . $offset);
            }

            return $this->raw('LIMIT ' . $count);
        }

        return $this();
    }

    /**
     * Build an SQL condition expressing that "$column is $value",
     * or "$column is in $value" if $value is an array. Handles null
     * and fragments like $dop("NOW()") correctly.
     *
     * @param string $column
     * @param mixed|array $value
     * @param bool $not
     * @return Fragment
     */
    public function is($column, $value, $not = false)
    {
        $bang = $not ? '!' : '';
        $or = $not ? ' AND ' : ' OR ';
        $novalue = $not ? '1=1' : '0=1';
        $not = $not ? ' NOT' : '';

        // always treat value as array
        if (!is_array($value)) {
            $value = array($value);
        }

        // always quote column identifier
        $column = $this->ident($column);

        if (count($value) === 1) {

      // use single column comparison if count is 1

            $value = $value[0];

            if ($value === null) {
                return $this->raw($column . ' IS' . $not . ' NULL');
            } else {
                return $this->raw($column . ' ' . $bang . '= ' . $this->value($value));
            }
        } elseif (count($value) > 1) {

      // if we have multiple values, use IN clause

            $values = array();
            $null = false;

            foreach ($value as $v) {
                if ($v === null) {
                    $null = true;
                } else {
                    $values[] = $this->value($v);
                }
            }

            $clauses = array();

            if (!empty($values)) {
                $clauses[] = $column . $not . ' IN (' . implode(', ', $values) . ')';
            }

            if ($null) {
                $clauses[] = $column . ' IS' . $not . ' NULL';
            }

            return $this->raw(implode($or, $clauses));
        }

        return $this->raw($novalue);
    }

    /**
     * Build an SQL condition expressing that "$column is not $value"
     * or "$column is not in $value" if $value is an array. Handles null
     * and fragments like $dop("NOW()") correctly.
     *
     * @param string $column
     * @param mixed|array $value
     * @return Fragment
     */
    public function isNot($column, $value)
    {
        return $this->is($column, $value, true);
    }

    /**
     * Build an assignment fragment, e.g. for UPDATE.
     *
     * @param array|\Traversable $data
     * @return Fragment
     */
    public function assign($data)
    {
        $assign = array();

        foreach ($data as $column => $value) {
            $assign[] = $this->ident($column) . ' = ' . $this->value($value);
        }

        return $this->raw(implode(', ', $assign));
    }

    /**
     * Quote a value for SQL.
     *
     * @param mixed $value
     * @return Fragment
     */
    public function value($value)
    {
        if (is_array($value)) {
            return $this->raw(implode(', ', array_map(array($this, 'value'), $value)));
        }

        if ($value instanceof Fragment) {
            return $value;
        }
        if ($value === null) {
            return $this('NULL');
        }

        $value = $this->format($value);

        if (is_float($value)) {
            $value = sprintf('%F', $value);
        }
        if ($value === false) {
            $value = '0';
        }
        if ($value === true) {
            $value = '1';
        }

        return $this->raw($this->pdo()->quote($value));
    }

    /**
     * Format a value for SQL, e.g. DateTime objects.
     *
     * @param mixed $value
     * @return string
     */
    public function format($value)
    {
        if ($value instanceof \DateTime) {
            $value = clone $value;
            $value->setTimeZone(new \DateTimeZone('UTC'));
            return $value->format('Y-m-d H:i:s');
        }

        return $value;
    }

    /**
     * Quote a table name.
     *
     * Default implementation is just quoting as an identifier.
     * Override for table prefixing etc.
     *
     * @param string $name
     * @return Fragment
     */
    public function table($name)
    {
        return $this->ident($name);
    }

    /**
     * Quote identifier(s).
     *
     * @param mixed $ident Must be 64 or less characters.
     * @return Fragment
     */
    public function ident($ident)
    {
        if (is_array($ident)) {
            return $this->raw(implode(', ', array_map(array($this, 'ident'), $ident)));
        }

        if ($ident instanceof Fragment) {
            return $ident;
        }

        if (strlen($ident) > 64) {
            throw new Exception('Identifier is longer than 64 characters');
        }

        $d = $this->identDelimiter;

        return $this->raw($d . str_replace($d, $d . $d, $ident) . $d);
    }

    /**
     * @see Connection::fragment
     */
    public function __invoke($sql = '', $params = array())
    {
        return $this->fragment($sql, $params);
    }

    /**
     * Create an SQL fragment, optionally with bound params.
     *
     * @param string|Fragment $sql
     * @param array $params
     * @return Fragment
     */
    public function fragment($sql = '', $params = array())
    {
        if ($sql instanceof Fragment) {
            return $sql->bind($params);
        }
        return new Fragment($this, $sql, $params);
    }

    /**
     * Create a raw SQL fragment, optionally with bound params.
     * The fragment will not be resolved, i.e. ?? and :: params ignored.
     *
     * @param string|Fragment $sql
     * @param array $params
     * @return Fragment
     */
    public function raw($sql = '', $params = array())
    {
        return $this($sql, $params)->raw();
    }

    //

    /**
     * Query last insert id.
     *
     * For PostgreSQL, the sequence name is required.
     *
     * @param string|null $sequence
     * @return mixed|null
     */
    public function lastInsertId($sequence = null)
    {
        return $this->pdo()->lastInsertId($sequence);
    }

    //

    /**
     * Execute a transaction.
     *
     * Nested transactions are treated as part of the outer transaction.
     *
     * @param callable $t The transaction body
     * @return mixed The return value of calling $t
     */
    public function transaction($t)
    {
        if (!is_callable($t)) {
            throw new Exception('Transaction must be callable');
        }

        $pdo = $this->pdo();

        if ($pdo->inTransaction()) {
            return call_user_func($t, $this);
        }

        $pdo->beginTransaction();

        try {
            $return = call_user_func($t, $this);
            $pdo->commit();
            return $return;
        } catch (\Exception $ex) {
            $pdo->rollBack();
            throw $ex;
        }
    }

    //

    /**
     * Return whether the given array or Traversable is empty.
     *
     * @param array|\Traversable
     * @return bool
     */
    public function empt($traversable)
    {
        foreach ($traversable as $_) {
            return false;
        }
        return true;
    }

    /**
     * Get list of all columns used in the given rows.
     *
     * @param array|\Traversable $rows
     * @return array
     */
    public function columns($rows)
    {
        if (!$rows) {
            return array();
        }
        $columns = array();

        foreach ($rows as $row) {
            foreach ($row as $column => $value) {
                $columns[$column] = true;
            }
        }

        return array_keys($columns);
    }

    /**
     * Return rows mapped to a column, multiple columns or using a function.
     *
     * @param array|Fragment|Result $rows Rows
     * @param int|string|array|function $fn Column, columns or function
     * @return array
     */
    public function map($rows, $fn)
    {
        if (is_callable(array($rows, 'fetchAll'))) {
            $rows = $rows->fetchAll();
        }

        if (is_array($fn)) {
            $columns = $fn;
            $fn = function ($row) use ($columns) {
                $mapped = array();
                foreach ($columns as $column) {
                    $mapped[$column] = @$row[$column];
                }
                return $mapped;
            };
        } elseif (!is_callable($fn)) {
            $column = $fn;
            $fn = function ($row) use ($column) {
                return $row[$column];
            };
        }

        return array_map($fn, $rows);
    }

    /**
     * Return rows filtered by column-value equality (non-strict) or function.
     *
     * @param array|Fragment|Result $rows Rows
     * @param int|string|array|function $fn Column, column-value pairs or function
     * @param mixed $value
     * @return array
     */
    public function filter($rows, $fn, $value = null)
    {
        if (is_callable(array($rows, 'fetchAll'))) {
            $rows = $rows->fetchAll();
        }

        if (is_array($fn)) {
            $columns = $fn;
            $fn = function ($row) use ($columns) {
                foreach ($columns as $column => $value) {
                    if (@$row[$column] != $value) {
                        return false;
                    }
                }
                return true;
            };
        } elseif (!is_callable($fn)) {
            $column = $fn;
            $fn = function ($row) use ($column, $value) {
                return @$row[$column] == $value;
            };
        }

        return array_values(array_filter($rows, $fn));
    }

    //

    /**
     * Called before executing a statement.
     *
     * The default implementation does nothing.
     *
     * @param Fragment $statement
     */
    public function beforeExec($statement)
    {
    }

    //

    /**
     * Get PDO driver name.
     *
     * @return string
     */
    public function driver()
    {
        return $this->pdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);
    }

    /**
     * Return wrapped PDO.
     *
     * @return \PDO
     */
    public function pdo()
    {
        return $this->pdo;
    }

    //

    /** @var string */
    const EMPTY_STATEMENT = 'SELECT 1 WHERE 0=1';
}

/**
 * Represents an arbitrary SQL fragment with bound params.
 * Can be prepared and executed.
 *
 * Immutable
 */
class Fragment implements \IteratorAggregate
{

  /**
   * Constructor
   *
   * @param Connection $conn
   * @param string $sql
   * @param array $params
   */
    public function __construct($conn, $sql = '', $params = array())
    {
        $this->conn = $conn;
        $this->sql = $sql;
        $this->params = $params;
    }

    /**
     * Return a new fragment with the given parameter(s).
     *
     * @param array|string|int $params Array of key-value parameters or parameter name
     * @param mixed $value If $params is a parameter name, bind to this value
     * @return Fragment
     */
    public function bind($params, $value = null)
    {
        if (empty($params) && $params !== 0) {
            return $this;
        }
        if (!is_array($params)) {
            return $this->bind(array($params => $value));
        }
        $clone = clone $this;
        foreach ($params as $key => $value) {
            $clone->params[$key] = $value;
        }
        return $clone;
    }

    /**
     * @see Fragment::exec
     */
    public function __invoke($params = null)
    {
        return $this->exec($params);
    }

    /**
     * Execute statement and return result.
     *
     * @param array $params
     * @return Result The prepared and executed result
     */
    public function exec($params = array())
    {
        return $this->prepare($params)->exec();
    }

    /**
     * Return prepared statement from this fragment.
     *
     * @param array $params
     * @return Result The prepared result
     */
    public function prepare($params = array())
    {
        return new Result($this->bind($params));
    }

    //

    /**
     * Execute, fetch and return first row, if any.
     *
     * @param int $offset Offset to skip
     * @return array|null
     */
    public function fetch($offset = 0)
    {
        return $this->exec()->fetch($offset);
    }

    /**
     * Execute, fetch and return all rows.
     *
     * @return array
     */
    public function fetchAll()
    {
        return $this->exec()->fetchAll();
    }

    //

    /**
     * Return new fragment with additional SELECT field or expression.
     *
     * @param string|Fragment $expr
     * @return Fragment
     */
    public function select($expr)
    {
        $before = (string) @$this->params['select'];
        if (!$before || (string) $before === '*') {
            $before = '';
        } else {
            $before .= ', ';
        }

        return $this->bind(array(
            'select' => $this->conn->raw(
                $before . $this->conn->ident(func_get_args())
            )
        ));
    }

    /**
     * Return new fragment with additional WHERE condition
     * (multiple are combined with AND).
     *
     * @param string|array $condition
     * @param mixed|array $params
     * @return Fragment
     */
    public function where($condition, $params = array())
    {
        return $this->bind(array(
            'where' => $this->conn->where($condition, $params, @$this->params['where'])
        ));
    }

    /**
     * Return new fragment with additional "$column is not $value" condition
     * (multiple are combined with AND).
     *
     * @param string|array $column
     * @param mixed $value
     * @return Fragment
     */
    public function whereNot($key, $value = null)
    {
        return $this->bind(array(
            'where' => $this->conn->whereNot($key, $value, @$this->params['where'])
        ));
    }

    /**
     * Return new fragment with additional ORDER BY column and direction.
     *
     * @param string $column
     * @param string $direction
     * @return Fragment
     */
    public function orderBy($column, $direction = "ASC")
    {
        return $this->bind(array(
            'orderBy' => $this->conn->orderBy($column, $direction, @$this->params['orderBy'])
        ));
    }

    /**
     * Return new fragment with result limit and optionally an offset.
     *
     * @param int|null $count
     * @param int|null $offset
     * @return Fragment
     */
    public function limit($count = null, $offset = null)
    {
        return $this->bind(array(
            'limit' => $this->conn->limit($count, $offset)
        ));
    }

    /**
     * Return new fragment with paged limit.
     *
     * Pages start at 1.
     *
     * @param int $pageSize
     * @param int $page
     * @return Fragment
     */
    public function paged($pageSize, $page)
    {
        return $this->limit($pageSize, ($page - 1) * $pageSize);
    }

    /**
     * Get connection.
     *
     * @return Connection
     */
    public function conn()
    {
        return $this->conn;
    }

    /**
     * Get resolved SQL string of this fragment.
     *
     * @return string
     */
    public function toString()
    {
        return $this->resolve()->sql;
    }

    /**
     * Get bound parameters.
     *
     * @return array
     */
    public function params()
    {
        return $this->params;
    }

    //

    /**
     * @see Fragment::toString
     */
    public function __toString()
    {
        try {
            return $this->toString();
        } catch (\Exception $ex) {
            return $ex->getMessage();
        }
    }

    //

    /**
     * Execute and return iterable Result.
     *
     * @return \Iterator
     */
    public function getIterator()
    {
        return $this->exec();
    }

    //

    /**
     * Return SQL fragment with all :: and ?? params resolved.
     *
     * @return Fragment
     */
    public function resolve()
    {
        if ($this->resolved) {
            return $this->resolved;
        }

        static $rx;

        if (!isset($rx)) {
            $rx = '(' . implode('|', array(
                '(\?\?)',                       // 1 double question mark
                '(\?)',                         // 2 question mark
                '(::[a-zA-Z_$][a-zA-Z0-9_$]*)', // 3 double colon marker
                '(:[a-zA-Z_$][a-zA-Z0-9_$]*)'   // 4 colon marker
            )) . ')s';
        }

        $this->resolveParams = array();
        $this->resolveOffset = 0;

        $resolved = preg_replace_callback($rx, array($this, 'resolveCallback'), $this->sql);

        $this->resolved = $this->conn->fragment($resolved, $this->resolveParams);
        $this->resolved->resolved = $this->resolved;

        $this->resolveParams = $this->resolveOffset = null;

        return $this->resolved;
    }

        $type = 1;
        while (!($string = $match[$type])) {
            ++$type;
        }

        $replacement = $string;
        $key = substr($string, 1);

        switch ($type) {
            case 1:
                if (array_key_exists($this->resolveOffset, $this->params)) {
                    $replacement = $conn->value($this->params[$this->resolveOffset]);
                } else {
                    throw new Exception('Unresolved parameter ' . $this->resolveOffset);
                }
                ++$this->resolveOffset;
                break;

            case 2:
                if (array_key_exists($this->resolveOffset, $this->params)) {
                    $this->resolveParams[] = $this->params[$this->resolveOffset];
                } else {
                    $this->resolveParams[] = null;
                }
                ++$this->resolveOffset;
                break;

            case 3:
                $key = substr($key, 1);
                if (array_key_exists($key, $this->params)) {
                    $replacement = $conn->value($this->params[$key]);
                } else {
                    throw new Exception('Unresolved parameter ' . $key);
                }
                break;

            case 4:
                if (array_key_exists($key, $this->params)) {
                    $this->resolveParams[$key] = $this->params[$key];
                }
                break;
        }

        // handle fragment insertion
        if ($replacement instanceof Fragment) {
            $replacement = $replacement->resolve();

            // merge fragment parameters
            // numbered params are appended
            // named params are merged only if the param does not exist yet
            foreach ($replacement->params() as $key => $value) {
                if (is_int($key)) {
                    $this->resolveParams[] = $value;
                } elseif (!array_key_exists($key, $this->params)) {
                    $this->resolveParams[$key] = $value;
                }
            }

            $replacement = $replacement->toString();
        }

        return $replacement;
    }

    /**
     * Create a raw SQL fragment copy of this fragment.
     * The new fragment will not be resolved, i.e. ?? and :: params ignored.
     *
     * @return Fragment
     */
    public function raw()
    {
        $clone = clone $this;
        $clone->resolved = $clone;
        return $clone;
    }

    /**
     * @ignore
     */
    public function __clone()
    {
        if ($this->resolved && $this->resolved->sql === $this->sql) {
            $this->resolved = $this;
        } else {
            $this->resolved = null;
        }
    }

}

/**
 * Represents a prepared and/or executed statement.
 *
 * Mutable because the contained PDOStatement is mutable.
 * Avoid using Results directly unless optimizing for performance.
 * Can only be iterated once per execution.
 * Following iterations yield no results.
 */
class Result implements \Iterator
{

  /**
   * Constructor
   *
   * @param Fragment $statement
   */
    public function __construct($statement)
    {
        $this->statement = $statement->resolve();
        $conn = $statement->conn();
        if ($statement->toString() !== $conn::EMPTY_STATEMENT) {
            $this->pdoStatement = $conn->pdo()->prepare($statement->toString());
        }
    }

    /**
     * Execute the prepared statement (again).
     *
     * @param array $params
     * @return $this
     */
    public function exec($params = array())
    {
        if (!$this->pdoStatement()) {
            return $this;
        }

        $statement = $this->statement->bind($params);
        $statement->conn()->beforeExec($statement);
        $this->pdoStatement()->execute($statement->params());

        return $this;
    }

    /**
     * Fetch next row.
     *
     * @param int $offset Offset in rows
     * @param int $orientation One of the PDO::FETCH_ORI_* constants
     * @return array|null
     */
    public function fetch($offset = 0, $orientation = null)
    {
        if (!$this->pdoStatement()) {
            return null;
        }
        $row = $this->pdoStatement()->fetch(
            \PDO::FETCH_ASSOC,
            isset($orientation) ? $orientation : \PDO::FETCH_ORI_NEXT,
            $offset
        );
        return $row ? $row : null;
    }

    /**
     * Fetch all rows.
     *
     * @return array
     */
    public function fetchAll()
    {
        if (!$this->pdoStatement()) {
            return array();
        }
        return $this->pdoStatement()->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Close the cursor in this result, if any.
     *
     * @return $this
     */
    public function close()
    {
        if ($this->pdoStatement()) {
            $this->pdoStatement()->closeCursor();
        }
        return $this;
    }

    /**
     * Return number of affected rows.
     *
     * @return int
     */
    public function affected()
    {
        if ($this->pdoStatement()) {
            return $this->pdoStatement()->rowCount();
        }
        return 0;
    }

    /**
     * @return \PDOStatement
     */
    public function pdoStatement()
    {
        return $this->pdoStatement;
    }

    //

    /**
     * @internal
     */
    public function current()
    {
        return $this->current;
    }

    /**
     * @internal
     */
    public function key()
    {
        return $this->key;
    }

    /**
     * @internal
     */
    public function next()
    {
        $this->current = $this->fetch();
        ++$this->key;
    }

    /**
     * @internal
     */
    public function rewind()
    {
        $this->current = $this->fetch();
        $this->key = 0;
    }

    /**
     * @internal
     */
    public function valid()
    {
        return $this->current;
    }

}

/**
 * Dop exception
 */
class Exception extends \Exception
{
}
```
