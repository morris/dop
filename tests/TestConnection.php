<?php

class TestConnection extends \Dop\Connection {

  function __construct( $pdo, $test ) {
    parent::__construct( $pdo );
    $this->test = $test;
  }

  function beforeExec( $statement ) {
    $return = parent::beforeExec( $statement );
    $this->test->beforeExec( $statement );
    return $return;
  }

  protected $test;

}
