<?php

namespace Dop;

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
   * @param string $sql
   * @param array $params
   */
  function __construct( $conn, $sql = '', array $params = array() ) {
    $this->conn = $conn;
    $this->sql = $sql;
    $this->params = $params;
  }

  /**
   * Return a new fragment with the given parameter(s).
   *
   * @param array|string $params
   * @param mixed $value
   * @return Fragment
   */
  function bind( $params, $value = null ) {
    if ( !is_array( $params ) ) {
      return $this->bind( array( $params => $value ) );
    }
    $clone = clone $this;
    foreach ( $params as $key => $value ) {
      $clone->params[ $key ] = $value;
    }
    return $clone;
  }

  /**
   * Return resolved fragment containing a prepared PDO statement.
   *
   * @param array $params
   * @return Fragment
   */
  function prepare() {
    if ( $this->pdoStatement ) return $this;
    $prepared = $this->resolve();
    $prepared->pdoStatement = $this->conn()->pdo()
      ->prepare( (string) $prepared );
    return $prepared;
  }

  /**
   * @see Fragment::exec
   */
  function __invoke( $params = null ) {
    return $this->exec( $params );
  }

  /**
   * Execute statement and return result.
   *
   * @param array $params
   * @return Result
   */
  function exec( $params = null ) {
    if ( $params !== null ) return $this->bind( $params )->exec();
    return $this->conn()->exec( $this );
  }

  /**
   * Execute and return all rows.
   *
   * @return array
   */
  function all() {
    return $this->exec()->all();
  }

  /**
   * Execute and return first row in result, if any.
   *
   * @return array|null
   */
  function first() {
    return $this->exec()->first();
  }

  /**
   * Execute and return rows mapped to a column, multiple columns or using
   * a function.
   *
   * @param string|array|function $fn
   * @return array
   */
  function map( $fn ) {
    return $this->exec()->map( $fn );
  }

  /**
   * Execute and return rows filtered by column-value equality (non-strict)
   * or function.
   *
   * @param string|array|function $fn
   * @param mixed $value
   * @return array
   */
  function filter( $fn, $value = null ) {
    return $this->exec()->filter( $fn, $value );
  }

  /**
   * Executed and return number of affected rows.
   *
   * @return int
   */
  function affected() {
    return $this->exec()->affected();
  }

  //

  /**
   * Return new fragment with additional SELECT field or expression.
   *
   * @param string|Fragment $expr
   * @return Fragment
   */
  function select( $expr ) {
    $before = (string) @$this->params[ 'select' ];
    if ( !$before || (string) $before === '*' ) {
      $before = '';
    } else {
      $before .= ', ';
    }

    return $this->bind( array(
      'select' => $this->conn->fragment(
        $before . $this->conn->ident( func_get_args() )
      )
    ) );
  }

  /**
   * Return new fragment with additional WHERE condition
   * (multiple are combined with AND).
   *
   * @param string|array $condition
   * @param mixed|array $params
   * @return Fragment
   */
  function where( $condition, $params = array() ) {
    return $this->bind( array(
      'where' => $this->conn->where( $condition, $params, @$this->params[ 'where' ] )
    ) );
  }

  /**
   * Return new fragment with additional "$column is not $value" condition
   * (multiple are combined with AND).
   *
   * @param string|array $column
   * @param mixed $value
   * @return Fragment
   */
  function whereNot( $key, $value = null ) {
    return $this->bind( array(
      'where' => $this->conn->whereNot( $key, $value, @$this->params[ 'where' ] )
    ) );
  }

  /**
   * Return new fragment with additional ORDER BY column and direction.
   *
   * @param string $column
   * @param string $direction
   * @return Fragment
   */
  function orderBy( $column, $direction = "ASC" ) {
    return $this->bind( array(
      'orderBy' => $this->conn->orderBy( $column, $direction, @$this->params[ 'orderBy' ] )
    ) );
  }

  /**
   * Return new fragment with result limit and optionally an offset.
   *
   * @param int|null $count
   * @param int|null $offset
   * @return Fragment
   */
  function limit( $count = null, $offset = null ) {
    return $this->bind( array(
      'limit' => $this->conn->limit( $count, $offset )
    ) );
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
  function paged( $pageSize, $page ) {
    return $this->limit( $pageSize, ( $page - 1 ) * $pageSize );
  }

  /**
   * Get associated connection.
   *
   * @return Connection
   */
  function conn() {
    return $this->conn;
  }

  /**
   * Get SQL string of this fragment.
   *
   * @return string
   */
  function string() {
    return $this->resolve()->sql;
  }

  /**
   * Get bound parameters.
   *
   * @return array
   */
  function params() {
    return $this->params;
  }

  /**
   * Return prepared internal PDO statement, if any.
   *
   * @return \PDOStatement
   */
  function pdoStatement() {
    return $this->pdoStatement;
  }

  /**
   * @see Fragment::string
   */
  function __toString() {
    try {
      return $this->string();
    } catch ( \Exception $ex ) {
      return $ex->getMessage();
    }
  }

  //

  /**
   * Execute and return result iterator
   *
   * @return \ArrayIterator
   */
  function getIterator() {
    return $this->exec()->getIterator();
  }

  /**
   * Execute and return row count of result
   *
   * @return int
   */
  function count() {
    return $this->exec()->count();
  }

  /**
   * Execute and return JSON representation of result
   *
   * @return array
   */
  function jsonSerialize() {
    return $this->exec()->jsonSerialize();
  }

  //

  /**
   * Return SQL fragment with all :: and ?? params resolved.
   *
   * @return Fragment
   */
  function resolve() {

    if ( $this->resolved ) return $this->resolved;

    static $rx;

    if ( !isset( $rx ) ) {

      $rx = '(' . implode( '|', array(
        '(\?\?)',                       // 1 double question mark
        '(\?)',                         // 2 question mark
        '(::[a-zA-Z_$][a-zA-Z0-9_$]*)', // 3 double colon marker
        '(:[a-zA-Z_$][a-zA-Z0-9_$]*)'   // 4 colon marker
      ) ) . ')s';

    }

    $this->resolveParams = array();
    $this->resolveOffset = 0;

    $resolved = preg_replace_callback( $rx, array( $this, 'resolveCallback' ), $this->sql );

    $this->resolved = $this->conn->fragment( $resolved, $this->resolveParams );
    $this->resolved->resolved = $this->resolved;

    $this->resolveParams = $this->resolveOffset = null;

    return $this->resolved;

  }

  /**
   * @param array $match
   * @return string
   */
  protected function resolveCallback( $match ) {

    $conn = $this->conn;

    $type = 1;
    while ( !( $string = $match[ $type ] ) ) ++$type;

    $replacement = $string;
    $key = substr( $string, 1 );

    switch ( $type ) {
    case 1:
      if ( array_key_exists( $this->resolveOffset, $this->params ) ) {
        $replacement = $conn->value( $this->params[ $this->resolveOffset ] );
      } else {
        throw new Exception( 'Unresolved parameter ' . $this->resolveOffset );
      }
      ++$this->resolveOffset;
      break;

    case 2:
      if ( array_key_exists( $this->resolveOffset, $this->params ) ) {
        $this->resolveParams[] = $this->params[ $this->resolveOffset ];
      } else {
        $this->resolveParams[] = null;
      }
      ++$this->resolveOffset;
      break;

    case 3:
      $key = substr( $key, 1 );
      if ( array_key_exists( $key, $this->params ) ) {
        $replacement = $conn->value( $this->params[ $key ] );
      } else {
        throw new Exception( 'Unresolved parameter ' . $key );
      }
      break;

    case 4:
      if ( array_key_exists( $key, $this->params ) ) {
        $this->resolveParams[ $key ] = $this->params[ $key ];
      }
      break;
    }

    // handle fragment insertion
    if ( $replacement instanceof Fragment ) {
      $replacement = $replacement->resolve();

      // merge fragment parameters
      // numbered params are appended
      // named params are merged only if the param does not exist yet
      foreach ( $replacement->params() as $key => $value ) {
        if ( is_int( $key ) ) {
          $this->resolveParams[] = $value;
        } else if ( !array_key_exists( $key, $this->params ) ) {
          $this->resolveParams[ $key ] = $value;
        }
      }
    }

    return (string) $replacement;

  }

  /**
   * Create a raw SQL fragment copy of this fragment.
   * The new fragment will not be resolved, i.e. ?? and :: params ignored.
   *
   * @return Fragment
   */
  function raw() {
    $clone = clone $this;
    $clone->resolved = $clone;
    return $clone;
  }

  /**
   * @ignore
   */
  function __clone() {
    if ( $this->resolved !== $this ) $this->resolved = null;
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

  /** @var \PDOStatement */
  protected $pdoStatement;

}
