<?php

require_once 'TestConnection.php';

class BaseTest extends PHPUnit_Framework_TestCase {

  function setUp() {

    self::$pdo->setAttribute( \PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION );

    $this->resetSchema();
    $this->resetData();

    $this->statements = array();
    $this->params = array();
    $this->conn = new TestConnection( self::$pdo, $this );

  }

  function beforeExec( $statement ) {

    $sql = $this->str( $statement );

    if ( strtoupper( substr( $sql, 0, 6 ) ) !== 'SELECT' ) self::$dirtyData = true;

    $this->statements[] = $sql;
    $this->params[] = $statement->resolve()->params();

  }

  function tearDown() {

    // always roll back active transactions
    $active = false;

    try {
      self::$pdo->rollBack();
      $active = true;
    } catch ( \Exception $ex ) {
      // ignore
    }

    if ( $active ) throw new Exception( 'There was an active transaction' );

  }

  function testDummy() {

  }

  function str( $mixed ) {
    return str_replace( '"', '`', trim( (string) $mixed ) );
  }

  //

  function resetSchema() {

    if ( !self::$dirtySchema ) return;

    self::$pdo->beginTransaction();

    if ( $this->driver() === 'sqlite' ) {
      $p = "INTEGER PRIMARY KEY AUTOINCREMENT";
    }

    if ( $this->driver() === 'mysql' ) {
      $p = "INTEGER PRIMARY KEY AUTO_INCREMENT";
    }

    if ( $this->driver() === 'pgsql' ) {
      $p = "SERIAL PRIMARY KEY";
    }

    $this->exec( "DROP TABLE IF EXISTS person" );

    $this->exec( "CREATE TABLE person (
      id $p,
      name varchar(30) NOT NULL
    )" );

    $this->exec( "DROP TABLE IF EXISTS post" );

    $this->exec( "CREATE TABLE post (
      id $p,
      author_id INTEGER DEFAULT NULL,
      editor_id INTEGER DEFAULT NULL,
      is_published INTEGER DEFAULT 0,
      date_published VARCHAR(30) DEFAULT NULL,
      title VARCHAR(30) NOT NULL
    )" );

    $this->exec( "DROP TABLE IF EXISTS category" );

    $this->exec( "CREATE TABLE category (
      id $p,
      title varchar(30) NOT NULL
    )" );

    $this->exec( "DROP TABLE IF EXISTS categorization" );

    $this->exec( "CREATE TABLE categorization (
      category_id INTEGER NOT NULL,
      post_id INTEGER NOT NULL
    )" );

    $this->exec( "DROP TABLE IF EXISTS dummy" );

    $this->exec( "CREATE TABLE dummy (
      id $p,
      test INTEGER,
      name VARCHAR(30)
    )" );

    self::$pdo->commit();
    self::$dirtySchema = false;
    self::$dirtyData = true;

  }

  function resetData() {

    if ( !self::$dirtyData ) return;

    self::$pdo->beginTransaction();

    // sequences

    if ( $this->driver() === 'sqlite' ) {

      $this->exec( "DELETE FROM sqlite_sequence WHERE name='person'" );
      $this->exec( "DELETE FROM sqlite_sequence WHERE name='post'" );
      $this->exec( "DELETE FROM sqlite_sequence WHERE name='category'" );
      $this->exec( "DELETE FROM sqlite_sequence WHERE name='dummy'" );

    }

    if ( $this->driver() === 'mysql' ) {

      $this->exec( "ALTER TABLE person AUTO_INCREMENT = 1" );
      $this->exec( "ALTER TABLE post AUTO_INCREMENT = 1" );
      $this->exec( "ALTER TABLE category AUTO_INCREMENT = 1" );
      $this->exec( "ALTER TABLE dummy AUTO_INCREMENT = 1" );

    }

    if ( $this->driver() === 'pgsql' ) {

      $this->exec( "SELECT setval('person_id_seq', 3)" );
      $this->exec( "SELECT setval('post_id_seq', 13)" );
      $this->exec( "SELECT setval('category_id_seq', 23)" );
      $this->exec( "SELECT setval('dummy_id_seq', 1, false)" );

    }

    // data

    // persons

    $this->exec( "DELETE FROM person" );

    $this->exec( "INSERT INTO person (id, name) VALUES (1, 'Writer')" );
    $this->exec( "INSERT INTO person (id, name) VALUES (2, 'Editor')" );
    $this->exec( "INSERT INTO person (id, name) VALUES (3, 'Chief Editor')" );

    // posts

    $this->exec( "DELETE FROM post" );

    $this->exec( "INSERT INTO post
      (id, title, date_published, is_published, author_id, editor_id)
      VALUES (11, 'Championship won', '2014-09-18', 1, 1, NULL)" );
    $this->exec( "INSERT INTO post
      (id, title, date_published, is_published, author_id, editor_id)
      VALUES (12, 'Foo released', '2014-09-15', 1, 1, 2)" );
    $this->exec( "INSERT INTO post
      (id, title, date_published, is_published, author_id, editor_id)
      VALUES (13, 'Bar released', '2014-09-21', 0, 2, 3)" );

    // categories

    $this->exec( "DELETE FROM category" );

    $this->exec( "INSERT INTO category (id, title) VALUES (21, 'Tech')" );
    $this->exec( "INSERT INTO category (id, title) VALUES (22, 'Sports')" );
    $this->exec( "INSERT INTO category (id, title) VALUES (23, 'Basketball')" );

    // categorization

    $this->exec( "DELETE FROM categorization" );

    $this->exec( "INSERT INTO categorization (category_id, post_id) VALUES (22, 11)" );
    $this->exec( "INSERT INTO categorization (category_id, post_id) VALUES (23, 11)" );
    $this->exec( "INSERT INTO categorization (category_id, post_id) VALUES (21, 12)" );
    $this->exec( "INSERT INTO categorization (category_id, post_id) VALUES (21, 13)" );

    // dummy

    $this->exec( "DELETE FROM dummy" );

    self::$pdo->commit();
    self::$dirtyData = false;

  }

  function exec( $s ) {
    return self::$pdo->exec( $s );
  }

  function driver() {
    return self::$pdo->getAttribute( \PDO::ATTR_DRIVER_NAME );
  }

  protected $statements = array();
  protected $params = array();

  public static $pdo;
  protected static $dirtySchema = true;
  protected static $dirtyData = true;

}
