# Dop

[ ![Build Status](https://travis-ci.org/morris/dop.svg?branch=master) ](https://travis-ci.org/morris/dop)
[ ![Test Coverage](https://codeclimate.com/github/morris/dop/badges/coverage.svg) ](https://codeclimate.com/github/morris/dop/coverage)

Dop is an immutable API on top of [PDO](http://php.net/manual/en/book.pdo.php)
to compose and execute SQL statements.

- Extended parameters (`::param` and `??`) allow binding to arbitrary values like arrays, `null` and SQL fragments.
- Provides helpers for writing common queries.
- Tested with **SQLite, PostgreSQL, and MySQL.**

## Usage

```php
// Connect to a database
$pdo = new PDO('sqlite:blog.sqlite3');
$dop = new Dop\Connection($pdo);

// Find published posts
$posts = $dop->query('post')->where('is_published = ?', [1])->fetchAll();

// Get categorizations
$categorizations = $dop(
    'SELECT * FROM categorization WHERE post_id IN (??)',
    [$dop->map($posts, 'id')]
)->fetchAll();

// Find posts with more than 3 categorizations
$catCount = $dop('SELECT COUNT(*) FROM categorization WHERE post_id = post.id');
$posts = $dop('SELECT * FROM post WHERE (::catCount) >= 3',
    ['catCount' => $catCount])->fetchAll();
```

## Parameters

Dop introduces additional parameter markers written as `::param` or `??`.
They allow arbitrary values like arrays, `null`, and other SQL fragments,
and enable powerful composition:

```php
$authorIds = [1, 2, 3];
$orderByTitle = $dop('order by title asc');
$posts = $dop('SELECT id FROM post WHERE author_id IN (??) ??',
    [$authorIds, $orderByTitle]);

// use $posts as sub query
$cats = $dop('SELECT * FROM categorization WHERE post_id IN (::posts)',
    ['posts' => $posts])->fetchAll();
```

Internally, these parameters are resolved before statement preparation.
Note that due to the current implementation using regular expressions,
you should *never* use quoted strings directly. Always use bound parameters.

## Installation

Dop requires PHP >= 5.3.0 and PDO.
Install via [composer](https://getcomposer.org/):

```
$ composer require morris/dop
```

## Reference

See [API.md](API.md) for a complete API reference.
