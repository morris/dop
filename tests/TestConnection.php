<?php

class TestConnection extends \Dop\Connection
{
    public function __construct($pdo, $test)
    {
        parent::__construct($pdo);
        $this->test = $test;
    }

    public function beforeExec($statement)
    {
        $return = parent::beforeExec($statement);
        $this->test->beforeExec($statement);
        return $return;
    }

    protected $test;
}
