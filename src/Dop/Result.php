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
class Result implements \Iterator {

  /**
   * Constructor
   *
   * @param Fragment $statement
   */
  function __construct( $statement ) {
    $this->statement = $statement->resolve();
    $conn = $statement->conn();
    if ( $statement->toString() !== $conn::EMPTY_STATEMENT ) {
      $this->pdoStatement = $conn->pdo()->prepare( $statement->toString() );
    }
  }

  /**
   * Execute the prepared statement (again).
   *
   * @param array $params
   * @return $this
   */
  function exec( $params = array() ) {

    if ( !$this->pdoStatement() ) return $this;

    $statement = $this->statement->bind( $params );
    $statement->conn()->beforeExec( $statement );
    $this->pdoStatement()->execute( $statement->params() );

    return $this;

  }

  /**
   * Fetch next row.
   *
   * @param int $offset Offset in rows
   * @param int $orientation One of the PDO::FETCH_ORI_* constants
   * @return array|null
   */
  function fetch( $offset = 0, $orientation = \PDO::FETCH_ORI_NEXT ) {
    if ( !$this->pdoStatement() ) return null;
    $row = $this->pdoStatement()->fetch( \PDO::FETCH_ASSOC, $orientation, $offset );
    return $row ? $row : null;
  }

  /**
   * Fetch all rows.
   *
   * @return array
   */
  function fetchAll() {
    if ( !$this->pdoStatement() ) return array();
    return $this->pdoStatement()->fetchAll( \PDO::FETCH_ASSOC );
  }

  /**
   * Close the cursor in this result, if any.
   *
   * @return $this
   */
  function close() {
    if ( $this->pdoStatement() ) $this->pdoStatement()->closeCursor();
    return $this;
  }

  /**
   * Return number of affected rows.
   *
   * @return int
   */
  function affected() {
    if ( $this->pdoStatement() ) return $this->pdoStatement()->rowCount();
    return 0;
  }

  /**
   * @return \PDOStatement
   */
  function pdoStatement() {
    return $this->pdoStatement;
  }

  //

  /**
   * @internal
   */
  function current() {
    return $this->current;
  }

  /**
   * @internal
   */
  function key() {
    return $this->key;
  }

  /**
   * @internal
   */
  function next() {
    $this->current = $this->fetch();
    ++$this->key;
  }

  /**
   * @internal
   */
  function rewind() {
    $this->current = $this->fetch();
    $this->key = 0;
  }

  /**
   * @internal
   */
  function valid() {
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
