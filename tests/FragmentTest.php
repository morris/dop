<?php

class SQLTest extends BaseTest {

  function testResolve() {

    $conn = $this->conn;

    $s = $conn( 'SELECT * FROM post, ::tables WHERE ::conds OR foo=:bar OR x in (::lol) :lol', array(
      'tables' => $conn->ident( array( 'a', 'b' ) ),
      'conds' => $conn->where( array( 'foo' => 'bar', 'x' => null ) ),
      'lol' => array( 1, 2, 3 )
    ) );

    $this->assertEquals(
      "SELECT * FROM post, `a`, `b` WHERE (`foo` = 'bar') AND `x` IS NULL OR foo=:bar OR x in ('1', '2', '3') :lol",
      str_replace( '"', '`', (string) $s )
    );

    $this->assertEquals(
      array( array( 1, 2, 3 ) ),
      array_values( $s->resolve()->params() )
    );

  }

  function testDoubleQuestionMark() {

    $conn = $this->conn;

    $s = $conn( 'SELECT * FROM post WHERE ?? > ??', array( $conn( 'dt' ), $conn( 'NOW()' ) ) );

    $this->assertEquals(
      "SELECT * FROM post WHERE dt > NOW()",
      (string) $s
    );

  }

  /**
     * @expectedException \Dop\Exception
   * @expectedExceptionMessage Unresolved parameter fields
     */
  function testUnresolved() {
    $conn = $this->conn;
    $conn( 'SELECT ::fields FROM ::post' )->resolve();
  }

  function testUnresolvedString() {
    $conn = $this->conn;
    $this->assertEquals(
      'Unresolved parameter 0',
      (string) $conn( 'SELECT ?? FROM ::post' )
    );
  }

  function testInvoke() {
    $conn = $this->conn;
    $posts = $conn( 'SELECT * FROM post' );
    $this->assertEquals( 3, count( $posts() ) );
  }

  function testWhere() {

    $conn = $this->conn;

    $conn->query( 'dummy' )->where( 'test', null )->first();
    $conn->query( 'dummy' )->where( 'test', 31 )->first();
    $conn->query( 'dummy' )->where( 'test', array( 1, 2, 3 ) )->first();
    $conn->query( 'dummy' )->where( array( 'test' => 31, 'id' => 1 ) )->first();
    $conn->query( 'dummy' )->where( 'test = 31' )->first();
    $conn->query( 'dummy' )->where( 'test = ?', array( 31 ) )->first();
    $conn->query( 'dummy' )->where( 'test = ?', array( 32 ) )->first();
    $conn->query( 'dummy' )->where( 'test = :param', array( 'param' => 31 ) )->first();
    $conn->query( 'dummy' )
      ->where( 'test < :a', array( 'a' => 31 ) )
      ->where( 'test > :b', array( 'b' => 0 ) )
      ->first();

    $this->assertEquals( array(
      "SELECT * FROM `dummy` WHERE `test` IS NULL",
      "SELECT * FROM `dummy` WHERE `test` = '31'",
      "SELECT * FROM `dummy` WHERE `test` IN ( '1', '2', '3' )",
      "SELECT * FROM `dummy` WHERE (`test` = '31') AND `id` = '1'",
      "SELECT * FROM `dummy` WHERE test = 31",
      "SELECT * FROM `dummy` WHERE test = ?",
      "SELECT * FROM `dummy` WHERE test = ?",
      "SELECT * FROM `dummy` WHERE test = :param",
      "SELECT * FROM `dummy` WHERE (test < :a) AND test > :b"
    ), $this->statements );

    $this->assertEquals( array(
      array(),
      array(),
      array(),
      array(),
      array(),
      array( 31 ),
      array( 32 ),
      array( 'param' => 31 ),
      array( 'a' => 31, 'b' => 0 )
    ), $this->params );

  }

  function testWhereNot() {

    $conn = $this->conn;

    $conn->query( 'dummy' )->whereNot( 'test', null )->first();
    $conn->query( 'dummy' )->whereNot( 'test', 31 )->first();
    $conn->query( 'dummy' )->whereNot( 'test', array( 1, 2, 3 ) )->first();
    $conn->query( 'dummy' )->whereNot( array( 'test' => 31, 'id' => 1 ) )->first();
    $conn->query( 'dummy' )
      ->whereNot( 'test', null )
      ->whereNot( 'test', 31 )
      ->first();

    $this->assertEquals( array(
      "SELECT * FROM `dummy` WHERE `test` IS NOT NULL",
      "SELECT * FROM `dummy` WHERE `test` != '31'",
      "SELECT * FROM `dummy` WHERE `test` NOT IN ( '1', '2', '3' )",
      "SELECT * FROM `dummy` WHERE (`test` != '31') AND `id` != '1'",
      "SELECT * FROM `dummy` WHERE (`test` IS NOT NULL) AND `test` != '31'"
    ), $this->statements );

    $this->assertEquals( array(
      array(),
      array(),
      array(),
      array(),
      array()
    ), $this->params );

  }

  function testOrderBy() {

    $conn = $this->conn;

    $conn->query( 'dummy' )->orderBy( 'id', 'DESC' )->orderBy( 'test' )->first();

    $this->assertEquals( array(
      "SELECT * FROM `dummy` WHERE 1=1 ORDER BY `id` DESC, `test` ASC",
    ), $this->statements );

  }

  /**
     * @expectedException \Dop\Exception
   * @expectedExceptionMessage Invalid ORDER BY direction: DESK
     */
  function testInvalidOrderBy() {
    $conn = $this->conn;
    $conn->query( 'dummy' )->orderBy( 'id', 'DESK' );
  }

  function testLimit() {

    $conn = $this->conn;

    $conn->query( 'dummy' )->limit( 3 )->first();
    $conn->query( 'dummy' )->limit( 3, 10 )->first();
    $conn->query( 'dummy' )->limit()->first();

    $this->assertEquals( array(
      "SELECT * FROM `dummy` WHERE 1=1  LIMIT 3",
      "SELECT * FROM `dummy` WHERE 1=1  LIMIT 3 OFFSET 10",
      "SELECT * FROM `dummy` WHERE 1=1"
    ), $this->statements );

  }

  function testPaged() {

    $conn = $this->conn;

    $conn->query( 'dummy' )->paged( 10, 1 )->first();
    $conn->query( 'dummy' )->paged( 10, 3 )->first();

    $this->assertEquals( array(
      "SELECT * FROM `dummy` WHERE 1=1  LIMIT 10 OFFSET 0",
      "SELECT * FROM `dummy` WHERE 1=1  LIMIT 10 OFFSET 20",
    ), $this->statements );

  }

  function testSelect() {

    $conn = $this->conn;

    $conn->query( 'dummy' )->select( 'test' )->first();
    $conn->query( 'dummy' )->select( 'test', 'id' )->first();
    $conn->query( 'dummy' )->select( 'test' )->select( 'id' )->first();

    $this->assertEquals( array(
      "SELECT `test` FROM `dummy` WHERE 1=1",
      "SELECT `test`, `id` FROM `dummy` WHERE 1=1",
      "SELECT `test`, `id` FROM `dummy` WHERE 1=1"
    ), $this->statements );

  }

  function testFirst() {
    $conn = $this->conn;
    $first = $conn->query( 'post' )->first();
    $this->assertEquals( 'Championship won', $first[ 'title' ] );
  }

  function testAll() {
    $conn = $this->conn;
    $this->assertEquals( 3, count( $conn->query( 'post' )->all() ) );
  }

  function testMap() {

    $conn = $this->conn;

    $this->assertEquals( array( 11, 12, 13 ), $conn->query( 'post' )->map( 'id' ) );

    $this->assertEquals( array(
      array(
        'id' => 11,
        'title' => 'Championship won'
      ),
      array(
        'id' => 12,
        'title' => 'Foo released'
      ),
      array(
        'id' => 13,
        'title' => 'Bar released'
      )
    ), $conn->query( 'post' )->map( array( 'id', 'title' ) ) );

    $this->assertEquals( array(
      'Championship won: 11',
      'Foo released: 12',
      'Bar released: 13'
    ), $conn->query( 'post' )->map( function ( $row ) {
      return $row[ 'title' ] . ': ' . $row[ 'id' ];
    } ) );

  }

  function testFilter() {

    $conn = $this->conn;

    $this->assertEquals( array(
      array(
        'id' => '11',
        'title' => 'Championship won',
        'is_published' => '1',
        'date_published' => '2014-09-18',
        'author_id' => '1',
        'editor_id' => null
      ),
    ), $conn->query( 'post' )->filter( 'id', 11 ) );

    $this->assertEquals( array(), $conn->query( 'post' )->filter( 'id', 99 ) );

    $this->assertEquals( array(
      array(
        'id' => '11',
        'title' => 'Championship won',
        'is_published' => '1',
        'date_published' => '2014-09-18',
        'author_id' => '1',
        'editor_id' => null
      ),
    ), $conn->query( 'post' )->filter( array( 'id' => 11, 'title' => 'Championship won' ) ) );

    $this->assertEquals( array(), $conn->query( 'post' )->filter(
      array( 'id' => 99, 'title' => 'Championship won' )
    ) );

    $this->assertEquals( 3, count( $conn->query( 'post' )->filter( array() ) ) );

    $this->assertEquals( 2, count( $conn->query( 'post' )->filter( function ( $row ) {
      return $row[ 'id' ] < 13;
    } ) ) );

  }

  function testAffected() {

    $conn = $this->conn;

    $this->assertEquals( 3, count( $conn->query( 'post' ) ) );

    $a = $conn( 'UPDATE post SET title = ?', array( 'fooz' ) )->affected();
    $this->assertEquals( 3, $a );

    $a = $conn( 'UPDATE post SET title = ? WHERE 0=1', array( 'test' ) )->affected();
    $this->assertEquals( 0, $a );

  }

  function testIterate() {

    $conn = $this->conn;

    foreach ( $conn->query( 'post' ) as $post ) {
      $this->assertTrue( !!$post[ 'id' ] );
    }

  }

  function testJsonSerialize() {

    // only supported for PHP >= 5.4.0
    if ( version_compare( phpversion(), '5.4.0', '<' ) ) return;

    $conn = $this->conn;

    $json = json_encode( $conn->query( 'post' )->select( 'id' ) );

    try {
      $expected = '[{"id":11},{"id":12},{"id":13}]';
      $this->assertEquals( $expected, $json );
    } catch ( \Exception $ex ) {
      $expected = '[{"id":"11"},{"id":"12"},{"id":"13"}]';
      $this->assertEquals( $expected, $json );
    }

  }

}
