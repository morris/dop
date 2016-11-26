<?php

class ResultTest extends BaseTest {

  function testFetch() {
    $conn = $this->conn;
    $posts = $conn->query( 'post' )->select( 'id' )->exec();
    $this->assertEquals( array( 'id' => '11' ), $posts->fetch() );
    $this->assertEquals( array( 'id' => '12' ), $posts->fetch() );
    $this->assertEquals( array( 'id' => '13' ), $posts->fetch() );
    $this->assertEquals( null, $posts->fetch() );
    $this->assertEquals( null, $posts->fetch() );
  }

  function testClose() {
    $conn = $this->conn;
    $posts = $conn->query( 'post' )->exec();
    $posts->fetch();
    $posts->close();
    $posts->close();
  }

  function testAffected() {
    $conn = $this->conn;
    $this->assertEquals( 0,
      $conn->insertBatch( 'post', array() )->exec()->affected() );
  }

}
