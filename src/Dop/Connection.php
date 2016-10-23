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
  function __construct( \PDO $pdo, $options = array() ) {

    $pdo->setAttribute( \PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION );
    $this->pdo = $pdo;

    $defaultIdentDelimiter = $this->driver() === 'mysql' ? '`' : '"';
    $this->identDelimiter = @$options[ 'identDelimiter' ] ?: $defaultIdentDelimiter;

  }

  /**
   * Returns a basic SELECT query for table $name.
   * If $id is given, return the row with that id.
   *
   * @param string $name
   * @return Fragment
   */
  function query( $table ) {

    return $this( 'SELECT ::select FROM ::table WHERE ::where ::orderBy ::limit', array(
      'select' => $this( '*' ),
      'table' => $this->table( $table ),
      'where' => $this->where(),
      'orderBy' => $this(),
      'limit' => $this()
    ) );

  }

  /**
   * Build an insert statement to insert a single row.
   *
   * @param string $table
   * @param array|\Traversable $row
   * @return Fragment
   */
  function insert( $table, $row ) {
    return $this->insertBatch( $table, array( $row ) );
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
  function insertBatch( $table, $rows ) {

    if ( count( $rows ) === 0 ) return $this( self::NOOP );

    $columns = $this->getColumns( $rows );

    $lists = array();

    foreach ( $rows as $row ) {
      $values = array();
      foreach ( $columns as $column ) {
        if ( array_key_exists( $column, $row ) ) {
          $values[] = $this->value( $row[ $column ] );
        } else {
          $values[] = 'DEFAULT';
        }
      }
      $lists[] = $this( "( " . implode( ", ", $values ) . " )" );
    }

    return $this( 'INSERT INTO ::table ( ::columns ) VALUES ::values', array(
      'table' => $this->table( $table ),
      'columns' => $this->ident( $columns ),
      'values' => $lists
    ) );

  }

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
  function insertPrepared( $table, $rows ) {

    if ( count( $rows ) === 0 ) return;

    $columns = $this->getColumns( $rows );

    $prepared = $this( 'INSERT INTO ::table ( ::columns ) VALUES ::values', array(
      'table' => $this->table( $table ),
      'columns' => $this->ident( $columns ),
      'values' => $this( '( ?' . str_repeat( ', ?', count( $columns ) - 1 ) . ' )' )
    ) )->prepare();

    foreach ( $rows as $row ) {
      $values = array();

      foreach ( $columns as $column ) {
        $values[] = (string) $this->format( @$row[ $column ] );
      }
      $prepared->exec( $values );
    }

  }

  /**
   * Get list of all columns used in the given rows.
   *
   * @param array|\Traversable $rows
   * @return array
   */
  protected function getColumns( $rows ) {

    $columns = array();

    foreach ( $rows as $row ) {
      foreach ( $row as $column => $value ) {
        $columns[ $column ] = true;
      }
    }

    return array_keys( $columns );

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
  function update( $table, $data, $where = array(), $params = array() ) {

    if ( empty( $data ) ) return $this( self::NOOP );

    return $this( 'UPDATE ::table SET ::set WHERE ::where ::limit', array(
      'table' => $this->table( $table ),
      'set' => $this->assign( $data ),
      'where' => $this->where( $where, $params ),
      'limit' => $this()
    ) );

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
  function delete( $table, $where = array(), $params = array() ) {

    return $this( 'DELETE FROM ::table WHERE ::where ::limit', array(
      'table' => $this->table( $table ),
      'where' => $this->where( $where, $params ),
      'limit' => $this()
    ) );

  }

  /**
   * Build a conditional expression fragment.
   *
   * @param array|string $condition
   * @param array|mixed $params
   * @param Fragment|null $before
   * @return Fragment
   */
  function where( $condition = null, $params = array(), Fragment $before = null ) {

    // empty condition evaluates to true
    if ( empty( $condition ) ) {
      return $before ? $before : $this( '1=1' );
    }

    // conditions in key-value array
    if ( is_array( $condition ) ) {
      $cond = $before;
      foreach ( $condition as $k => $v ) {
        $cond = $this->where( $k, $v, $cond );
      }
      return $cond;
    }

    // shortcut for basic "column is (in) value"
    if ( preg_match( '/^[a-z0-9_.`"]+$/i', $condition ) ) {
      $condition = $this->is( $condition, $params );
    } else {
      $condition = $this( $condition, $params );
    }

    if ( $before && (string) $before !== '1=1' ) {
      return $this( '(' . $before . ') AND ::__condition', $before->resolve()->params() )
        ->bind( '__condition', $condition );
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
  function whereNot( $key, $value = array(), Fragment $before = null ) {

    // key-value array
    if ( is_array( $key ) ) {
      $cond = $before;
      foreach ( $key as $k => $v ) {
        $cond = $this->whereNot( $k, $v, $cond );
      }
      return $cond;
    }

    // "column is not (in) value"
    $condition = $this->isNot( $key, $value );

    if ( $before && (string) $before !== '1=1' ) {
      return $this( '(' . $before . ') AND ::__condition', $before->resolve()->params() )
        ->bind( '__condition', $condition );
    }

    return $condition;

  }

  /**
   * Build an ORDER BY fragment.
   *
   * @param string $column
   * @param string $direction
   * @param Fragment|null $before
   * @return Fragment
   */
  function orderBy( $column, $direction = 'ASC', Fragment $before = null ) {

    if ( !preg_match( '/^asc|desc$/i', $direction ) ) {
      throw new Exception( 'Invalid ORDER BY direction: ' . $direction );
    }

    return $this(
      ( $before && (string) $before !== '' ? ( $before . ', ' ) : 'ORDER BY ' ) .
      $this->ident( $column ) . ' ' . $direction
    );

  }

  /**
   * Build a LIMIT fragment.
   *
   * @param int $count
   * @param int $offset
   * @return Fragment
   */
  function limit( $count = null, $offset = null ) {

    if ( $count !== null ) {

      $count = intval( $count );
      if ( $count < 1 ) throw new Exception( 'Invalid LIMIT count: ' + $count );

      if ( $offset !== null ) {
        $offset = intval( $offset );
        if ( $offset < 0 ) throw new Exception( 'Invalid LIMIT offset: ' + $offset );

        return $this( 'LIMIT ' . $count . ' OFFSET ' . $offset );
      }

      return $this( 'LIMIT ' . $count );
    }

    return $this();

  }

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
  function is( $column, $value, $not = false ) {

    $bang = $not ? '!' : '';
    $or = $not ? ' AND ' : ' OR ';
    $novalue = $not ? '1=1' : '0=1';
    $not = $not ? ' NOT' : '';

    // always treat value as array
    if ( !is_array( $value ) ) {
      $value = array( $value );
    }

    // always quote column identifier
    $column = $this->ident( $column );

    if ( count( $value ) === 1 ) {

      // use single column comparison if count is 1

      $value = $value[ 0 ];

      if ( $value === null ) {
        return $this( $column . ' IS' . $not . ' NULL' );
      } else {
        return $this( $column . ' ' . $bang . '= ' . $this->value( $value ) );
      }

    } else if ( count( $value ) > 1 ) {

      // if we have multiple values, use IN clause

      $values = array();
      $null = false;

      foreach ( $value as $v ) {

        if ( $v === null ) {
          $null = true;
        } else {
          $values[] = $this->value( $v );
        }

      }

      $clauses = array();

      if ( !empty( $values ) ) {
        $clauses[] = $column . $not . ' IN ( ' . implode( ', ', $values ) . ' )';
      }

      if ( $null ) {
        $clauses[] = $column . ' IS' . $not . ' NULL';
      }

      return $this( implode( $or, $clauses ) );

    }

    return $this( $novalue );

  }

  /**
   * Build an SQL condition expressing that "$column is not $value"
   * or "$column is not in $value" if $value is an array. Handles null
   * and fragments like $conn( "NOW()" ) correctly.
   *
   * @param string $column
   * @param mixed|array $value
   * @return Fragment
   */
  function isNot( $column, $value ) {
    return $this->is( $column, $value, true );
  }

  /**
   * Build an assignment fragment, e.g. for UPDATE.
   *
   * @param array|\Traversable $data
   * @return Fragment
   */
  function assign( $data ) {

    $assign = array();

    foreach ( $data as $column => $value ) {
      $assign[] = $this->ident( $column ) . ' = ' . $this->value( $value );
    }

    return $this( implode( ', ', $assign ) );

  }

  /**
   * Quote a value for SQL.
   *
   * @param mixed $value
   * @return Fragment
   */
  function value( $value ) {

    if ( is_array( $value ) ) {
      return $this( implode( ', ', array_map( array( $this, 'value' ), $value ) ) );
    }

    if ( $value instanceof Fragment ) return $value;
    if ( $value === null ) return $this( 'NULL' );

    $value = $this->format( $value );

    if ( is_float( $value ) ) $value = sprintf( '%F', $value );
    if ( $value === false ) $value = '0';
    if ( $value === true ) $value = '1';

    return $this( $this->pdo()->quote( $value ) );

  }

  /**
   * Format a value for SQL, e.g. DateTime objects.
   *
   * @param mixed $value
   * @return string
   */
  function format( $value ) {

    if ( $value instanceof \DateTime ) {
      $value = clone $value;
      $value->setTimeZone( new \DateTimeZone( 'UTC' ) );
      return $value->format( 'Y-m-d H:i:s' );
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
  function table( $name ) {
    return $this->ident( $name );
  }

  /**
   * Quote identifier(s).
   *
   * @param mixed $ident
   * @return Fragment
   */
  function ident( $ident ) {

    if ( is_array( $ident ) ) {
      return $this( implode( ', ', array_map( array( $this, 'ident' ), $ident ) ) );
    }

    if ( $ident instanceof Fragment ) return $ident;

    if ( strlen( $ident ) > 64 ) {
      throw new Exception( 'Identifier is longer than 64 characters' );
    }

    $d = $this->identDelimiter;

    return $this( $d . str_replace( $d, $d . $d, $ident ) . $d );

  }

  /**
   * @see Connection::fragment
   */
  function __invoke( $sql = '', $params = array() ) {
    return $this->fragment( $sql, $params );
  }

  /**
   * Create an SQL fragment, optionally with bound params.
   *
   * @param string|Fragment $sql
   * @param array $params
   * @return Fragment
   */
  function fragment( $sql = '', $params = array() ) {
    if ( $sql instanceof Fragment ) return $sql->bind( $params );
    return new Fragment( $this, $sql, $params );
  }

  /**
   * Create a raw SQL fragment, optionally with bound params.
   * The fragment will not be resolved, i.e. ?? and :: params ignored.
   *
   * @param string|Fragment $sql
   * @param array $params
   * @return Fragment
   */
  function raw( $sql = '', $params = array() ) {
    return $this( $sql, $params )->raw();
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
  function lastInsertId( $sequence = null ) {
    return $this->pdo()->lastInsertId( $sequence );
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
  function transaction( $t ) {

    if ( !is_callable( $t ) ) {
      throw new Exception( 'Transaction must be callable' );
    }

    $pdo = $this->pdo();

    if ( $pdo->inTransaction() ) return call_user_func( $t, $this );

    $pdo->beginTransaction();

    try {
      $return = call_user_func( $t, $this );
      $pdo->commit();
      return $return;
    } catch ( \Exception $ex ) {
      $pdo->rollBack();
      throw $ex;
    }

  }

  /**
   * Execute an SQL statement and return the result.
   *
   * @param string|Fragment $statement
   * @param array $params
   * @return Result
   */
  function exec( $statement, $params = array() ) {

    $statement = $this( $statement, $params );
    $resolved = $statement->resolve();

    if ( (string) $resolved === self::NOOP ) {
      return $this->result();
    }

    $result = $this->beforeExec( $statement );
    if ( $result ) return $result;

    $prepared = $resolved->prepare();
    $pdoStatement = $prepared->pdoStatement();
    $pdoStatement->execute( $prepared->params() );

    $rows = array();
    $affected = 0;

    try {
      $rows = $pdoStatement->fetchAll( \PDO::FETCH_ASSOC );
    } catch ( \Exception $ex ) {
      // ignore
    }

    try {
      $affected = $pdoStatement->rowCount();
    } catch ( \Exception $ex ) {
      // ignore
    }

    return $this->result( $rows, $affected );

  }

  /**
   * Create a result.
   *
   * @param array $rows
   * @param int $affected
   * @return Result
   */
  function result( $rows = array(), $affected = 0 ) {
    return new Result( $rows, $affected );
  }

  /**
   * Called before executing a statement.
   *
   * If a value is returned, execution is skipped and the value is returned
   * instead. The default implementation does nothing.
   *
   * @param Fragment $statement
   * @return mixed
   */
  function beforeExec( $statement ) {

  }

  //

  /**
   * Get PDO driver name.
   *
   * @return string
   */
  function driver() {
    return $this->pdo()->getAttribute( \PDO::ATTR_DRIVER_NAME );
  }

  /**
   * Return wrapped PDO.
   *
   * @return \PDO
   */
  function pdo() {
    return $this->pdo;
  }

  //

  /** @var \PDO */
  protected $pdo;

  /** @var string */
  protected $identDelimiter;

  /** @var string */
  const NOOP = 'SELECT 1 WHERE 0=1';

}
