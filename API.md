# API

```php
<?php

namespace Dop;

/**
 * Represents a database connection and a factory for SQL fragments.
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
   * @return Result The prepared result
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
   * @param string $direction Must be ASC or DESC
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
   * @param mixed $ident Must be 64 or less characters.
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

  /**
   * Create a raw SQL fragment, optionally with bound params.
   * The fragment will not be resolved, i.e. ?? and :: params ignored.
   *
   * @param string|Fragment $sql
   * @param array $params
   * @return Fragment
   */
  function raw( $sql = '', $params = array() );

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

  //

  /**
   * Return whether the given array or Traversable is empty.
   *
   * @param array|\Traversable
   * @return bool
   */
  function empt( $traversable );

  /**
   * Get list of all columns used in the given rows.
   *
   * @param array|\Traversable $rows
   * @return array
   */
  function columns( $rows );

  /**
   * Return rows mapped to a column, multiple columns or using a function.
   *
   * @param array|Fragment|Result $rows Rows
   * @param int|string|array|function $fn Column, columns or function
   * @return array
   */
  function map( $rows, $fn );

  /**
   * Return rows filtered by column-value equality (non-strict) or function.
   *
   * @param array|Fragment|Result $rows Rows
   * @param int|string|array|function $fn Column, column-value pairs or function
   * @param mixed $value
   * @return array
   */
  function filter( $rows, $fn, $value = null );

  //

  /**
   * Called before executing a statement.
   *
   * The default implementation does nothing.
   *
   * @param Fragment $statement
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
  const EMPTY_STATEMENT = 'SELECT 1 WHERE 0=1';

}

/**
 * Represents an arbitrary SQL fragment with bound params.
 * Can be prepared and executed.
 *
 * Immutable
 */
class Fragment implements \IteratorAggregate {

  /**
   * Constructor
   *
   * @param Connection $conn
   * @param string $sql
   * @param array $params
   */
  function __construct( $conn, $sql = '', $params = array() );

  /**
   * Return a new fragment with the given parameter(s).
   *
   * @param array|string|int $params Array of key-value parameters or parameter name
   * @param mixed $value If $params is a parameter name, bind to this value
   * @return Fragment
   */
  function bind( $params, $value = null );

  /**
   * @see Fragment::exec
   */
  function __invoke( $params = null );

  /**
   * Execute statement and return result.
   *
   * @param array $params
   * @return Result The prepared and executed result
   */
  function exec( $params = array() );

  /**
   * Return prepared statement from this fragment.
   *
   * @param array $params
   * @return Result The prepared result
   */
  function prepare( $params = array() );

  //

  /**
   * Execute, fetch and return first row, if any.
   *
   * @param int $offset Offset to skip
   * @return array|null
   */
  function fetch( $offset = 0 );

  /**
   * Execute, fetch and return all rows.
   *
   * @return array
   */
  function fetchAll();

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
   * Get connection.
   *
   * @return Connection
   */
  function conn();

  /**
   * Get resolved SQL string of this fragment.
   *
   * @return string
   */
  function toString();

  /**
   * Get bound parameters.
   *
   * @return array
   */
  function params();

  //

  /**
   * @see Fragment::toString
   */
  function __toString();

  //

  /**
   * Execute and return iterable Result.
   *
   * @return \Iterator
   */
  function getIterator();

  //

  /**
   * Return SQL fragment with all :: and ?? params resolved.
   *
   * @return Fragment
   */
  function resolve();

  /**
   * Create a raw SQL fragment copy of this fragment.
   * The new fragment will not be resolved, i.e. ?? and :: params ignored.
   *
   * @return Fragment
   */
  function raw();

  /**
   * @ignore
   */
  function __clone();

}

/**
 * Represents a prepared and/or executed statement.
 *
 * Mutable because the contained PDOStatement is mutable.
 * Avoid using Results directly unless optimizing for performance.
 * Can only be iterated once per execution.
 * Following iterations yield no results.
 */
class Result implements \Iterator {

  /**
   * Constructor
   *
   * @param Fragment $statement
   */
  function __construct( $statement );

  /**
   * Execute the prepared statement (again).
   *
   * @param array $params
   * @return $this
   */
  function exec( $params = array() );

  /**
   * Fetch next row.
   *
   * @param int $offset Offset in rows
   * @param int $orientation One of the PDO::FETCH_ORI_* constants
   * @return array|null
   */
  function fetch( $offset = 0, $orientation = \PDO::FETCH_ORI_NEXT );

  /**
   * Fetch all rows.
   *
   * @return array
   */
  function fetchAll();

  /**
   * Close the cursor in this result, if any.
   *
   * @return $this
   */
  function close();

  /**
   * Return number of affected rows.
   *
   * @return int
   */
  function affected();

  /**
   * @return \PDOStatement
   */
  function pdoStatement();

  //

  /**
   * @internal
   */
  function current();

  /**
   * @internal
   */
  function key();

  /**
   * @internal
   */
  function next();

  /**
   * @internal
   */
  function rewind();

  /**
   * @internal
   */
  function valid();

}

/**
 * Dop exception
 */
class Exception extends \Exception {

}
```
