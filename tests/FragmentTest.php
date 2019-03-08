<?php

class FragmentTest extends BaseTest
{
    public function testResolve()
    {
        $conn = $this->conn;

        $s = $conn('SELECT * FROM post, ::tables WHERE ::conds OR foo=:bar OR x in (::lol) :lol', array(
            'tables' => $conn->ident(array('a', 'b')),
            'conds' => $conn->where(array('foo' => 'bar', 'x' => null)),
            'lol' => array(1, 2, 3)
        ));

        $this->assertEquals(
            "SELECT * FROM post, `a`, `b` WHERE (`foo` = 'bar') AND `x` IS NULL OR foo=:bar OR x in ('1', '2', '3') :lol",
            str_replace('"', '`', (string) $s)
        );

        $this->assertEquals(
            array(array(1, 2, 3)),
            array_values($s->resolve()->params())
        );
    }

    public function testDoubleQuestionMark()
    {
        $conn = $this->conn;

        $s = $conn('SELECT * FROM post WHERE ?? > ??', array($conn('dt'), $conn('NOW()')));

        $this->assertEquals(
            "SELECT * FROM post WHERE dt > NOW()",
            (string) $s
        );
    }

    public function testUnresolved()
    {
        $this->expectException(\Dop\Exception::class);
        $this->expectExceptionMessage('Unresolved parameter fields');

        $conn = $this->conn;
        $conn('SELECT ::fields FROM ::post')->resolve();
    }

    public function testUnresolvedString()
    {
        $conn = $this->conn;
        $this->assertEquals(
            'Unresolved parameter 0',
            (string) $conn('SELECT ?? FROM ::post')
        );
    }

    public function testInvoke()
    {
        $conn = $this->conn;
        $posts = $conn('SELECT * FROM post');
        $this->assertEquals(3, count($posts()->fetchAll()));
    }

    public function testWhere()
    {
        $conn = $this->conn;

        $conn->query('dummy')->where('test', null)->fetch();
        $conn->query('dummy')->where('test', 31)->fetch();
        $conn->query('dummy')->where('test', array(1, 2, 3))->fetch();
        $conn->query('dummy')->where(array('test' => 31, 'id' => 1))->fetch();
        $conn->query('dummy')->where('test = 31')->fetch();
        $conn->query('dummy')->where('test = ?', array(31))->fetch();
        $conn->query('dummy')->where('test = ?', array(32))->fetch();
        $conn->query('dummy')->where('test = :param', array('param' => 31))->fetch();
        $conn->query('dummy')
            ->where('test < :a', array('a' => 31))
            ->where('test > :b', array('b' => 0))
            ->fetch();

        $this->assertEquals(array(
            "SELECT * FROM `dummy` WHERE `test` IS NULL",
            "SELECT * FROM `dummy` WHERE `test` = '31'",
            "SELECT * FROM `dummy` WHERE `test` IN ('1', '2', '3')",
            "SELECT * FROM `dummy` WHERE (`test` = '31') AND `id` = '1'",
            "SELECT * FROM `dummy` WHERE test = 31",
            "SELECT * FROM `dummy` WHERE test = ?",
            "SELECT * FROM `dummy` WHERE test = ?",
            "SELECT * FROM `dummy` WHERE test = :param",
            "SELECT * FROM `dummy` WHERE (test < :a) AND test > :b"
    ), $this->statements);

        $this->assertEquals(array(
            array(),
            array(),
            array(),
            array(),
            array(),
            array(31),
            array(32),
            array('param' => 31),
            array('a' => 31, 'b' => 0)
    ), $this->params);
    }

    public function testWhereNot()
    {
        $conn = $this->conn;

        $conn->query('dummy')->whereNot('test', null)->fetch();
        $conn->query('dummy')->whereNot('test', 31)->fetch();
        $conn->query('dummy')->whereNot('test', array(1, 2, 3))->fetch();
        $conn->query('dummy')->whereNot(array('test' => 31, 'id' => 1))->fetch();
        $conn->query('dummy')
            ->whereNot('test', null)
            ->whereNot('test', 31)
            ->fetch();

        $this->assertEquals(array(
            "SELECT * FROM `dummy` WHERE `test` IS NOT NULL",
            "SELECT * FROM `dummy` WHERE `test` != '31'",
            "SELECT * FROM `dummy` WHERE `test` NOT IN ('1', '2', '3')",
            "SELECT * FROM `dummy` WHERE (`test` != '31') AND `id` != '1'",
            "SELECT * FROM `dummy` WHERE (`test` IS NOT NULL) AND `test` != '31'"
        ), $this->statements);

        $this->assertEquals(array(
            array(),
            array(),
            array(),
            array(),
            array()
        ), $this->params);
    }

    public function testOrderBy()
    {
        $conn = $this->conn;

        $conn->query('dummy')->orderBy('id', 'DESC')->orderBy('test')->fetch();

        $this->assertEquals(array(
            "SELECT * FROM `dummy` WHERE 1=1 ORDER BY `id` DESC, `test` ASC",
        ), $this->statements);
    }

    public function testInvalidOrderBy()
    {
        $this->expectException(\Dop\Exception::class);
        $this->expectExceptionMessage('Invalid ORDER BY direction: DESK');

        $conn = $this->conn;
        $conn->query('dummy')->orderBy('id', 'DESK');
    }

    public function testLimit()
    {
        $conn = $this->conn;

        $conn->query('dummy')->limit(3)->fetch();
        $conn->query('dummy')->limit(3, 10)->fetch();
        $conn->query('dummy')->limit()->fetch();

        $this->assertEquals(array(
            "SELECT * FROM `dummy` WHERE 1=1  LIMIT 3",
            "SELECT * FROM `dummy` WHERE 1=1  LIMIT 3 OFFSET 10",
            "SELECT * FROM `dummy` WHERE 1=1"
        ), $this->statements);
    }

    public function testPaged()
    {
        $conn = $this->conn;

        $conn->query('dummy')->paged(10, 1)->fetch();
        $conn->query('dummy')->paged(10, 3)->fetch();

        $this->assertEquals(array(
            "SELECT * FROM `dummy` WHERE 1=1  LIMIT 10 OFFSET 0",
            "SELECT * FROM `dummy` WHERE 1=1  LIMIT 10 OFFSET 20",
        ), $this->statements);
    }

    public function testSelect()
    {
        $conn = $this->conn;

        $conn->query('dummy')->select('test')->fetch();
        $conn->query('dummy')->select('test', 'id')->fetch();
        $conn->query('dummy')->select('test')->select('id')->fetch();

        $this->assertEquals(array(
            "SELECT `test` FROM `dummy` WHERE 1=1",
            "SELECT `test`, `id` FROM `dummy` WHERE 1=1",
            "SELECT `test`, `id` FROM `dummy` WHERE 1=1"
    ), $this->statements);
    }

    public function testFirst()
    {
        $conn = $this->conn;
        $first = $conn->query('post')->fetch();
        $this->assertEquals('Championship won', $first[ 'title' ]);
    }

    public function testAll()
    {
        $conn = $this->conn;
        $this->assertEquals(3, count($conn->query('post')->fetchAll()));
    }

    public function testAffected()
    {
        $conn = $this->conn;

        $this->assertEquals(3, count($conn->query('post')->fetchAll()));

        $a = $conn('UPDATE post SET title = ?', array('fooz'))->exec()->affected();
        $this->assertEquals(3, $a);

        $a = $conn('UPDATE post SET title = ? WHERE 0=1', array('test'))->exec()->affected();
        $this->assertEquals(0, $a);
    }

    public function testIterate()
    {
        $conn = $this->conn;

        $ids = array();
        foreach ($conn->query('post') as $i => $post) {
            $ids[ $i ] = $post[ 'id' ];
        }

        $this->assertEquals(array('11', '12', '13'), $ids);
    }
}
