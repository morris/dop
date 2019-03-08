<?php

require 'BaseTest.php';
require 'vendor/autoload.php';

BaseTest::$pdo = new \PDO('sqlite:tests/test.sqlite');
