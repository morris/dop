# API

```php
<?php

namespace Dop;

/**
 * Represents a database connection, capable of writing SQL fragments and
 * executing statements.
 *
 * Immutable
 */
class Connection {

  /**
   * Constructor
   *
   * @param \PDO $pdo
   * @param array $options
   */
  function __construct( \PDO $pdo, $options = array() );

  /**
   * Returns a basic SELECT query for table $name.
   * If $id is given, return the row with that id.
   *
   * @param string $name
   * @return Fragment
   */
  function query( $table );

  /**
   * Build an insert statement to insert a single row.
   *
   * @param string $table
   * @param array|\Traversable $row
   * @return Fragment
   */
  function insert( $table, $row );

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
  function insertBatch( $table, $rows );

  /**
   * Insert multiple rows using a prepared statement (directly executed).
   *
   * Prepare a statement and execute it once per row using bound params.
   * Does not support SQL fragments in row data.
   *
   * @param string $table
   * @param array|\Traversable $rows
   * @return Result The insert result for the last row
   */
  function insertPrepared( $table, $rows );

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
  function update( $table, $data, $where = array(), $params = array() );

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
  function delete( $table, $where = array(), $params = array() );

  /**
   * Build a conditional expression fragment.
   *
   * @param array|string $condition
   * @param array|mixed $params
   * @param Fragment|null $before
   * @return Fragment
   */
  function where( $condition = null, $params = array(), Fragment $before = null );

  /**
   * Build a negated conditional expression fragment.
   *
   * @param string $key
   * @param mixed $value
   * @param Fragment|null $before
   * @return Fragment
   */
  function whereNot( $key, $value = array(), Fragment $before = null );

  /**
   * Build an ORDER BY fragment.
   *
   * @param string $column
   * @param string $direction
   * @param Fragment|null $before
   * @return Fragment
   */
  function orderBy( $column, $direction = 'ASC', Fragment $before = null );

  /**
   * Build a LIMIT fragment.
   *
   * @param int $count
   * @param int $offset
   * @return Fragment
   */
  function limit( $count = null, $offset = null );

  /**
   * Build an SQL condition expressing that "$column is $value",
   * or "$column is in $value" if $value is an array. Handles null
   * and fragments like $conn( "NOW()" ) correctly.
   *
   * @param string $column
   * @param mixed|array $value
   * @param bool $not
   * @return Fragment
   */
  function is( $column, $value, $not = false );

  /**
   * Build an SQL condition expressing that "$column is not $value"
   * or "$column is not in $value" if $value is an array. Handles null
   * and fragments like $conn( "NOW()" ) correctly.
   *
   * @param string $column
   * @param mixed|array $value
   * @return Fragment
   */
  function isNot( $column, $value );

  /**
   * Build an assignment fragment, e.g. for UPDATE.
   *
   * @param array|\Traversable $data
   * @return Fragment
   */
  function assign( $data );

  /**
   * Quote a value for SQL.
   *
   * @param mixed $value
   * @return Fragment
   */
  function value( $value );

  /**
   * Format a value for SQL, e.g. DateTime objects.
   *
   * @param mixed $value
   * @return string
   */
  function format( $value );

  /**
   * Quote a table name.
   *
   * Default implementation is just quoting as an identifier.
   * Override for table prefixing etc.
   *
   * @param string $name
   * @return Fragment
   */
  function table( $name );

  /**
   * Quote identifier(s).
   *
   * @param mixed $ident
   * @return Fragment
   */
  function ident( $ident );

  /**
   * @see Connection::fragment
   */
  function __invoke( $sql = '', $params = array() );

  /**
   * Create an SQL fragment, optionally with bound params.
   *
   * @param string|Fragment $sql
   * @param array $params
   * @return Fragment
   */
  function fragment( $sql = '', $params = array() );

  //

  /**
   * Query last insert id.
   *
   * For PostgreSQL, the sequence name is required.
   *
   * @param string|null $sequence
   * @return mixed|null
   */
  function lastInsertId( $sequence = null );

  //

  /**
   * Execute a transaction.
   *
   * Nested transactions are treated as part of the outer transaction.
   *
   * @param callable $t The transaction body
   * @return mixed The return value of calling $t
   */
  function transaction( $t );

  /**
   * Execute an SQL statement and return the result.
   *
   * @param string|Fragment $statement
   * @param array $params
   * @return Result
   */
  function exec( $statement, $params = array() );

  /**
   * Create a result.
   *
   * @param array $rows
   * @param int $affected
   * @return Result
   */
  function result( $rows = array(), $affected = 0 );

  /**
   * Called before executing a statement.
   *
   * If a value is returned, execution is skipped and the value is returned
   * instead. The default implementation does nothing.
   *
   * @param Fragment $statement
   * @return mixed
   */
  function beforeExec( $statement );

  //

  /**
   * Get PDO driver name.
   *
   * @return string
   */
  function driver();

  /**
   * Return wrapped PDO.
   *
   * @return \PDO
   */
  function pdo();

  //

  /** @var string */
  const NOOP = 'SELECT 1 WHERE 0=1';

}

/**
 * Represents an arbitrary SQL fragment with bound params.
 * Can be prepared and executed.
 *
 * Immutable
 */
class Fragment implements \IteratorAggregate, \Countable, \JsonSerializable {

  /**
   * Constructor
   *
   * @param Connection $conn
   */
  function __construct( $conn, $sql = '', array $params = array() );

  /**
   * Return a new fragment with the given parameter(s).
   *
   * @param array|string $params
   * @param mixed $value
   * @return Fragment
   */
  function bind( $params, $value = null );

  /**
   * Return resolved fragment containing a prepared PDO statement.
   *
   * @param array $params
   * @return Fragment
   */
  function prepare();

  /**
   * @see Fragment::exec
   */
  function __invoke( $params = null );

  /**
   * Execute statement and return result.
   *
   * @param array $params
   * @return Result
   */
  function exec( $params = null );

  /**
   * Execute and return all rows.
   *
   * @return array
   */
  function all();

  /**
   * Execute and return first row in result, if any.
   *
   * @return array|null
   */
  function first();

  /**
   * Execute and return rows mapped to a column, multiple columns or using
   * a function.
   *
   * @param string|array|function $fn
   * @return array
   */
  function map( $fn );

  /**
   * Execute and return rows filtered by column-value equality (non-strict)
   * or function.
   *
   * @param string|array|function $fn
   * @param mixed $value
   * @return array
   */
  function filter( $fn, $value = null );

  /**
   * Executed and return number of affected rows.
   *
   * @return int
   */
  function affected();

  //

  /**
   * Return new fragment with additional SELECT field or expression.
   *
   * @param string|Fragment $expr
   * @return Fragment
   */
  function select( $expr );

  /**
   * Return new fragment with additional WHERE condition
   * (multiple are combined with AND).
   *
   * @param string|array $condition
   * @param mixed|array $params
   * @return Fragment
   */
  function where( $condition, $params = array() );

  /**
   * Return new fragment with additional "$column is not $value" condition
   * (multiple are combined with AND).
   *
   * @param string|array $column
   * @param mixed $value
   * @return Fragment
   */
  function whereNot( $key, $value = null );

  /**
   * Return new fragment with additional ORDER BY column and direction.
   *
   * @param string $column
   * @param string $direction
   * @return Fragment
   */
  function orderBy( $column, $direction = "ASC" );

  /**
   * Return new fragment with result limit and optionally an offset.
   *
   * @param int|null $count
   * @param int|null $offset
   * @return Fragment
   */
  function limit( $count = null, $offset = null );

  /**
   * Return new fragment with paged limit.
   *
   * Pages start at 1.
   *
   * @param int $pageSize
   * @param int $page
   * @return Fragment
   */
  function paged( $pageSize, $page );

  /**
   * Get associated connection.
   *
   * @return Connection
   */
  function conn();

  /**
   * Get SQL string of this fragment.
   *
   * @return string
   */
  function string();

  /**
   * Get bound parameters.
   *
   * @return array
   */
  function params();

  /**
   * Return prepared internal PDO statement, if any.
   *
   * @return \PDOStatement
   */
  function pdoStatement();

  /**
   * @see Fragment::string
   */
  function __toString();

  //

  /**
   * Execute and return result iterator
   *
   * @return \ArrayIterator
   */
  function getIterator();

  /**
   * Execute and return row count of result
   *
   * @return int
   */
  function count();

  /**
   * Execute and return JSON representation of result
   *
   * @return array
   */
  function jsonSerialize();

  //

  /**
   * Return SQL fragment with all :: and ?? params resolved.
   *
   * @return Fragment
   */
  function resolve();

  /**
   *
   */
  function __clone();

}

/**
 * Represents the result of a SQL statement.
 * May contain rows and the number of affected rows.
 *
 * Immutable
 */
class Result implements \IteratorAggregate, \Countable, \JsonSerializable {

  /**
   * Constructor
   */
  function __construct( $rows = array(), $affected = 0 );

  /**
   * Return all rows as an array.
   *
   * @return array
   */
  function all();

  /**
   * Return first row in result, if any.
   *
   * @return array
   */
  function first();

  /**
   * Return rows mapped to a column, multiple columns or using a function.
   *
   * @param int|string|array|function $fn Column, columns or function
   * @return array
   */
  function map( $fn );

  /**
   * Return rows filtered by column-value equality (non-strict) or function.
   *
   * @param int|string|array|function $fn Column, column-value pairs or function
   * @param mixed $value
   * @return array
   */
  function filter( $fn, $value = null );

  /**
   * Return number of affected rows.
   *
   * @return int
   */
  function affected();

  //

  /**
   * Return row iterator.
   *
   * @return \ArrayIterator
   */
  function getIterator();

  /**
   * Return row count.
   *
   * @return int
   */
  function count();

  /**
   * Return JSON representation of rows.
   *
   * @return array
   */
  function jsonSerialize();

}

/**
 * Dop exception
 */
class Exception extends \Exception {

}
```
