<?php

class TestConnection extends \Dop\Connection
{
    public function __construct($pdo, $test)
    {
        parent::__construct($pdo);
        $this->test = $test;
    }

    public function execCallback($statement, $callback)
    {
        $this->test->beforeExec($statement);
        $callback();
    }

    protected $test;
}
