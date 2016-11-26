<?php

class ConnectionTest extends BaseTest {

  function testDriver() {
    $conn = $this->conn;
    $this->assertEquals( $this->driver(), $conn->driver() );
  }

  function testTransaction() {
    $conn = $this->conn;
    $conn->transaction( function () {

    } );
    $conn->transaction( function () {

    } );
  }

  /**
   * @expectedException \Dop\Exception
   * @expectedExceptionMessage Transaction must be callable
   */
  function testTransactionCallable() {
    $conn = $this->conn;
    $conn->transaction( 'notcallable' );
  }

  /**
   * @expectedException \Exception
   * @expectedExceptionMessage test
   */
  function testTransactionException() {
    $conn = $this->conn;
    $conn->transaction( function () {
      throw new \Exception( 'test' );
    } );
  }

  /**
   * @expectedException \Exception
   * @expectedExceptionMessage test
   */
  function testTransactionNestedException() {
    $conn = $this->conn;
    $conn->transaction( function () use ( $conn ) {
      $conn->transaction( function () {
        throw new \Exception( 'test' );
      } );
    } );
  }

  function testValue() {

    $conn = $this->conn;

    $a = array_map( array( $this, 'str' ), array(
      $conn->value( null ),
      $conn->value( $conn( 'NULL' ) ),
      $conn->value( false ),
      $conn->value( true ),
      $conn->value( 0 ),
      $conn->value( 1 ),
      $conn->value( 0.0 ),
      $conn->value( 3.1 ),
      $conn->value( '1' ),
      $conn->value( 'foo' ),
      $conn->value( '' ),
      $conn->value( $conn() ),
      $conn->value( $conn( 'BAR' ) ),
    ) );

    $ex = array(
      "NULL",
      "NULL",
      "'0'",
      "'1'",
      "'0'",
      "'1'",
      "'0.000000'",
      "'3.100000'",
      "'1'",
      "'foo'",
      "''",
      "",
      "BAR",
    );

    $this->assertEquals( $ex, $a );

  }

  function testIdent() {

    $conn = $this->conn;

    $d = $this->driver() === 'mysql' ? '`' : '"';

    $a = array_map( array( $this, 'str' ), array(
      $conn->ident( 'foo' ),
      $conn->ident( 'foo.bar' ),
      $conn->ident( 'foo' . $d . '.bar' ),
    ) );

    $ex = array(
      '`foo`',
      '`foo.bar`',
      '`foo``.bar`'
    );

    $this->assertEquals( $ex, $a );

  }

  /**
   * @expectedException \Dop\Exception
   * @expectedExceptionMessage Identifier is longer than 64 characters
   */
  function testIdentTooLong() {

    $conn = $this->conn;
    $conn->ident( str_repeat( 'x', 65 ) );

  }

  function testTransactions() {

    $conn = $this->conn;

    $conn->transaction( function ( $conn ) {
      $conn->transaction( function ( $conn ) {

      } );
    } );

  }

  function testIs() {

    $conn = $this->conn;

    $a = array_map( array( $this, 'str' ), array(
      $conn->is( 'foo', null ),
      $conn->is( 'foo', 0 ),
      $conn->is( 'foo', 'bar' ),
      $conn->is( 'foo', new \DateTime( '2015-01-01 01:00:00' ) ),
      $conn->is( 'foo', $conn( 'BAR' ) ),
      $conn->is( 'foo', array( 'x', 'y' ) ),
      $conn->is( 'foo', array( 'x', null ) ),
      $conn->is( 'foo', array( 'x' ) ),
      $conn->is( 'foo', array() ),
      $conn->is( 'foo', array( null ) ),
    ) );

    $ex = array(
      "`foo` IS NULL",
      "`foo` = '0'",
      "`foo` = 'bar'",
      "`foo` = '2015-01-01 01:00:00'",
      "`foo` = BAR",
      "`foo` IN ( 'x', 'y' )",
      "`foo` IN ( 'x' ) OR `foo` IS NULL",
      "`foo` = 'x'",
      "0=1",
      "`foo` IS NULL",
    );

    $this->assertEquals( $ex, $a );

  }

  function testIsNot() {

    $conn = $this->conn;

    $a = array_map( array( $this, 'str' ), array(
      $conn->isNot( 'foo', null ),
      $conn->isNot( 'foo', 0 ),
      $conn->isNot( 'foo', 'bar' ),
      $conn->isNot( 'foo', new \DateTime( '2015-01-01 01:00:00' ) ),
      $conn->isNot( 'foo', $conn( 'BAR' ) ),
      $conn->isNot( 'foo', array( 'x', 'y' ) ),
      $conn->isNot( 'foo', array( 'x', null ) ),
      $conn->isNot( 'foo', array( 'x' ) ),
      $conn->isNot( 'foo', array() ),
      $conn->isNot( 'foo', array( null ) )
    ) );

    $ex = array(
      "`foo` IS NOT NULL",
      "`foo` != '0'",
      "`foo` != 'bar'",
      "`foo` != '2015-01-01 01:00:00'",
      "`foo` != BAR",
      "`foo` NOT IN ( 'x', 'y' )",
      "`foo` NOT IN ( 'x' ) AND `foo` IS NOT NULL",
      "`foo` != 'x'",
      "1=1",
      "`foo` IS NOT NULL"
    );

    $this->assertEquals( $ex, $a );

  }

  function testInsert() {

    $conn = $this->conn;

    $conn->transaction( function ( $conn ) {
      $conn->insert( 'dummy', array( 'id' => 2, 'test' => 42 ) )->exec();
      foreach ( array(
        array( 'id' => 3,  'test' => 1 ),
        array( 'id' => 4,  'test' => 2 ),
        array( 'id' => 5,  'test' => 3 )
      ) as $row ) $conn->insert( 'dummy', $row )->exec();
    } );

    $this->assertEquals( array(
      "INSERT INTO `dummy` ( `id`, `test` ) VALUES ( '2', '42' )",
      "INSERT INTO `dummy` ( `id`, `test` ) VALUES ( '3', '1' )",
      "INSERT INTO `dummy` ( `id`, `test` ) VALUES ( '4', '2' )",
      "INSERT INTO `dummy` ( `id`, `test` ) VALUES ( '5', '3' )"
    ), $this->statements );

  }

  function testInsertPrepared() {

    $conn = $this->conn;
    $test = $this;

    $conn->transaction( function ( $conn ) use ( $test ) {
      $result = $conn->insertPrepared( 'dummy', array(
        array( 'test' => 1 ),
        array( 'test' => 2 ),
        array( 'test' => 3 )
      ) );
      $test->assertTrue( intval( $conn->lastInsertId( 'dummy_id_seq' ) ) > 0 );
    } );

    $this->assertEquals( array(
      "INSERT INTO `dummy` ( `test` ) VALUES ( ? )",
      "INSERT INTO `dummy` ( `test` ) VALUES ( ? )",
      "INSERT INTO `dummy` ( `test` ) VALUES ( ? )"
    ), $this->statements );

    $this->assertEquals( array(
      array( 1 ),
      array( 2 ),
      array( 3 )
    ), $this->params );

    $this->assertEquals( array(
      array( 'test' => 1 ),
      array( 'test' => 2 ),
      array( 'test' => 3 )
    ), $conn->query( 'dummy' )->select( 'test' )->fetchAll() );

  }

  function testInsertBatch() {

    $conn = $this->conn;

    $insert = $conn->insertBatch( 'dummy', array(
      array( 'test' => 1 ),
      array( 'test' => 2 ),
      array( 'test' => 3 )
    ) );

    $this->beforeExec( $insert );
    $this->assertEquals(
      array( "INSERT INTO `dummy` ( `test` ) VALUES ( '1' ), ( '2' ), ( '3' )" ),
      $this->statements
    );

    if ( $this->driver() === 'sqlite' ) return;

    $insert->exec();

    $this->assertEquals( array(
      array( 'test' => 1 ),
      array( 'test' => 2 ),
      array( 'test' => 3 )
    ), $conn->query( 'dummy' )->select( 'test' )->fetchAll() );

  }

  function testInsertBatchDefault() {

    $conn = $this->conn;

    $insert = $conn->insertBatch( 'dummy', array(
      array( 'test' => 1 ),
      array(),
      array( 'test' => 3 )
    ) );

    $this->beforeExec( $insert );
    $this->assertEquals(
      array( "INSERT INTO `dummy` ( `test` ) VALUES ( '1' ), ( DEFAULT ), ( '3' )" ),
      $this->statements
    );

    if ( $this->driver() === 'sqlite' ) return;

    $insert->exec();

    $this->assertEquals( array(
      array( 'test' => 1 ),
      array( 'test' => 0 ),
      array( 'test' => 3 )
    ), $conn->query( 'dummy' )->select( 'test' )->fetchAll() );

  }

  function testUpdate() {

    $conn = $this->conn;
    $self = $this;

    $conn->transaction( function ( $conn ) use ( $self ) {
      $conn->update( 'dummy', array() )->exec();
      $conn->update( 'dummy', array( 'test' => 42 ) )->exec();
      $conn->update( 'dummy', array( 'test' => 42 ) )->where( 'test', 1 )->exec();
      $conn->update( 'dummy', new \ArrayIterator( array( 'test' => 42 ) ) )->where( 'test', 1 )->exec();
    } );

    $this->assertEquals( array(
      "UPDATE `dummy` SET `test` = '42' WHERE 1=1",
      "UPDATE `dummy` SET `test` = '42' WHERE `test` = '1'",
      "UPDATE `dummy` SET `test` = '42' WHERE `test` = '1'"
    ), $this->statements );

  }

  function testDelete() {

    $conn = $this->conn;
    $self = $this;

    $conn->transaction( function ( $conn ) use ( $self ) {
      $conn->delete( 'dummy' )->exec();
      $conn->delete( 'dummy', array( 'test' => 1 ) )->exec();
    } );

    $this->assertEquals( array(
      "DELETE FROM `dummy` WHERE 1=1",
      "DELETE FROM `dummy` WHERE `test` = '1'"
    ), $this->statements );

  }

  function testRaw() {

    $conn = $this->conn;

    $raw = "SELET * FROM dummy WHERE test='::test' AND foo=::test and ?? = ?";
    $frag = $conn->raw( $raw );

    $this->assertEquals( $frag, $frag->resolve() );
    $this->assertEquals( $raw, (string) $frag );
    $this->assertEquals( $raw, (string) $frag->resolve() );

    $frag = $frag->bind( 0, 1 );

    $this->assertEquals( $frag, $frag->resolve() );
    $this->assertEquals( $raw, (string) $frag );
    $this->assertEquals( $raw, (string) $frag->resolve() );

  }

  function testReadme() {

    $dop = $this->conn;

    // Find published posts
    $posts = $dop->query( 'post' )->where( 'is_published = ?', array( 1 ) )->fetchAll();

    // Get categorizations
    $categorizations = $dop(
      'select * from categorization where post_id in ( ?? )',
      array( $dop->map( $posts, 'id' ) )
    )->fetchAll();

    // Find posts with more than 3 categorizations
    $catCount = $dop( 'select count( * ) from categorization where post_id = post.id' );
    $posts = $dop( 'select * from post where ( ::catCount ) >= 3',
      array( 'catCount' => $catCount ) )->fetchAll();

    //

    $authorIds = array( 1, 2, 3 );
    $orderByTitle = $dop( 'order by title asc' );
    $posts = $dop( 'select id from post where author_id in ( ?? ) ??',
      array( $authorIds, $orderByTitle ) );

    // use $posts as sub query
    $cats = $dop( 'select * from categorization where post_id in ( ::posts )',
      array( 'posts' => $posts ) )->exec();

  }

  function testInjection() {

    $dop = $this->conn;

    $dop->insert( 'dummy', array(
      'name' => 'hello?'
    ) )->exec();

    $dop->update( 'dummy', array(
      'name' => 'hello? ::world'
    ) )->exec();

    $dop->query( 'dummy' )
      ->where( 'name', 'hello?' )
      ->where( 'name', '::world' )
      ->exec();

    $dop( '::insert', array(
      'insert' => $dop->insert( 'dummy', array(
        'name' => 'hello?'
      ) )
    ) )->exec();

  }

  function testMap() {

    $conn = $this->conn;

    $this->assertEquals( array( 11, 12, 13 ), $conn->map( $conn->query( 'post' ), 'id' ) );

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
    ), $conn->map( $conn->query( 'post' ), array( 'id', 'title' ) ) );

    $this->assertEquals( array(
      'Championship won: 11',
      'Foo released: 12',
      'Bar released: 13'
    ), $conn->map( $conn->query( 'post' ), function ( $row ) {
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
    ), $conn->filter( $conn->query( 'post' ), 'id', 11 ) );

    $this->assertEquals( array(), $conn->filter( $conn->query( 'post' ), 'id', 99 ) );

    $this->assertEquals( array(
      array(
        'id' => '11',
        'title' => 'Championship won',
        'is_published' => '1',
        'date_published' => '2014-09-18',
        'author_id' => '1',
        'editor_id' => null
      ),
    ), $conn->filter( $conn->query( 'post' )->exec(), array( 'id' => 11, 'title' => 'Championship won' ) ) );

    $this->assertEquals( array(), $conn->filter( $conn->query( 'post' ),
      array( 'id' => 99, 'title' => 'Championship won' )
    ) );

    $this->assertEquals( 3, count( $conn->filter( $conn->query( 'post' ), array() ) ) );

    $this->assertEquals( 2, count( $conn->filter( $conn->query( 'post' ), function ( $row ) {
      return $row[ 'id' ] > 11;
    } ) ) );

    $notFirst = $conn->filter( $conn->query( 'post' ), function ( $row ) {
      return $row[ 'id' ] > 11;
    } );

    $this->assertTrue( isset( $notFirst[ 0 ] ) );
    $this->assertTrue( isset( $notFirst[ 1 ] ) );
    $this->assertFalse( isset( $notFirst[ 3 ] ) );

  }

}
