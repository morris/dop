<?php

namespace Dop;

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

    /**
     * @param array $match
     * @return string
     */
    protected function resolveCallback($match)
    {
        $conn = $this->conn;

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

    //

    /** @var Connection */
    protected $conn;

    /** @var string */
    protected $sql;

    /** @var array */
    protected $params;

    /** @var Fragment */
    protected $resolved;

    /** @var int */
    protected $resolveOffset;

    /** @var array */
    protected $resolveParams;
}
