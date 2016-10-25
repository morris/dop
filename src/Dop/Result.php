<?php

namespace Dop;

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
  function __construct( $rows = array(), $affected = 0 ) {
    $this->rows = $rows;
    $this->count = count( $this->rows );
    $this->affected = $affected;
  }

  /**
   * Return all rows as an array.
   *
   * @return array
   */
  function all() {
    return $this->rows;
  }

  /**
   * Return first row in result, if any.
   *
   * @return array
   */
  function first() {
    return $this->count > 0 ? $this->rows[ 0 ] : null;
  }

  /**
   * Return rows mapped to a column, multiple columns or using a function.
   *
   * @param int|string|array|function $fn Column, columns or function
   * @return array
   */
  function map( $fn ) {

    if ( is_array( $fn ) ) {
      $columns = $fn;
      $fn = function ( $row ) use ( $columns ) {
        $mapped = array();
        foreach ( $columns as $column ) {
          $mapped[ $column ] = @$row[ $column ];
        }
        return $mapped;
      };
    } else if ( !is_callable( $fn ) ) {
      $column = $fn;
      $fn = function ( $row ) use ( $column ) {
        return $row[ $column ];
      };
    }

    return array_map( $fn, $this->rows );

  }

  /**
   * Return rows filtered by column-value equality (non-strict) or function.
   *
   * @param int|string|array|function $fn Column, column-value pairs or function
   * @param mixed $value
   * @return array
   */
  function filter( $fn, $value = null ) {

    if ( is_array( $fn ) ) {
      $columns = $fn;
      $fn = function ( $row ) use ( $columns ) {
        foreach ( $columns as $column => $value ) {
          if ( @$row[ $column ] != $value ) return false;
        }
        return true;
      };
    } else if ( !is_callable( $fn ) ) {
      $column = $fn;
      $fn = function ( $row ) use ( $column, $value ) {
        return @$row[ $column ] == $value;
      };
    }

    return array_values( array_filter( $this->rows, $fn ) );

  }

  /**
   * Return number of affected rows.
   *
   * @return int
   */
  function affected() {
    return $this->affected;
  }

  //

  /**
   * Return row iterator.
   *
   * @return \ArrayIterator
   */
  function getIterator() {
    return new \ArrayIterator( $this->rows );
  }

  /**
   * Return row count.
   *
   * @return int
   */
  function count() {
    return $this->count;
  }

  /**
   * Return JSON representation of rows.
   *
   * @return array
   */
  function jsonSerialize() {
    return $this->rows;
  }

  //

  /** @var array */
  protected $rows = array();

  /** @var int */
  protected $count = 0;

  /** @var int */
  protected $affected = 0;

}
