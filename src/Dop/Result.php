<?php

namespace Dop;

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
    }

    /**
     * Execute the prepared statement (again).
     *
     * @param array $params
     * @return $this
     */
    public function exec($params = array())
    {
        $pdoStatement = $this->pdoStatement();

        if (!$pdoStatement) {
            return $this;
        }

        $statement = $this->statement->bind($params);

        $this->conn()->execCallback($statement, function () use ($pdoStatement, $statement) {
            $pdoStatement->execute($statement->params());
        });

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
        $pdoStatement = $this->pdoStatement();

        if (!$pdoStatement) {
            return null;
        }

        $row = $pdoStatement->fetch(
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
        $pdoStatement = $this->pdoStatement();

        if (!$pdoStatement) {
            return array();
        }

        return $pdoStatement->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Close the cursor in this result, if any.
     *
     * @return $this
     */
    public function close()
    {
        $pdoStatement = $this->pdoStatement();

        if ($pdoStatement) {
            $pdoStatement->closeCursor();
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
        $pdoStatement = $this->pdoStatement();

        if ($pdoStatement) {
            return $pdoStatement->rowCount();
        }

        return 0;
    }

    /**
     * Get this result's connection.
     *
     * @return Connection
     */
    public function conn()
    {
        return $this->statement->conn();
    }

    /**
     * Get this result's statement.
     *
     * @return Fragment
     */
    public function statement()
    {
        return $this->statement;
    }

    /**
     * @return \PDOStatement
     */
    public function pdoStatement()
    {
        if ($this->pdoStatement) {
            return $this->pdoStatement;
        }

        $conn = $this->conn();
        $statement = $this->statement->toString();

        if ($statement !== $conn::EMPTY_STATEMENT) {
            $this->pdoStatement = $conn->pdo()->prepare($statement);
        }

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

    //

    /** @var Fragment */
    protected $statement;

    /** @var \PDOStatement */
    protected $pdoStatement;

    /** @var array */
    protected $current;

    /** @var int */
    protected $key;
}
