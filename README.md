# Dop

[![Build Status](https://travis-ci.org/morris/dop.svg?branch=master)](https://travis-ci.org/morris/dop)
[![Test Coverage](https://codeclimate.com/github/morris/dop/badges/coverage.svg)](https://codeclimate.com/github/morris/dop/coverage)

Dop is an immutable API on top of [PDO](http://php.net/manual/en/book.pdo.php)
to compose and execute SQL statements.

- Extended parameters (`::param` and `??`) allow binding to arbitrary values like arrays, `null` and SQL fragments.
- Provides helpers for writing common queries, e.g. selects, inserts, updates, deletes.
- Tested with **SQLite, PostgreSQL, and MySQL.**

## Installation

Dop requires PHP >= 5.3.0 and PDO.
Install via [composer](https://getcomposer.org/):

```sh
composer require morris/dop
```

## Usage

```php
// Connect to a database
$pdo = new PDO('sqlite:blog.sqlite3');
$dop = new Dop\Connection($pdo);

// Find posts by author IDs using Dop parametrization
$authorIds = [1, 2, 3];
$orderByTitle = $dop('ORDER BY title ASC');
$posts = $dop(
    'SELECT * FROM post WHERE author_id IN (??) ??',
    [$authorIds, $orderByTitle]
)->fetchAll();

// Find published posts using Dop helpers for common queries
$posts = $dop->query('post')->where('is_published = ?', [1])->fetchAll();

// Get categorizations of posts using Dop's map function
$categorizations = $dop(
    'SELECT * FROM categorization WHERE post_id IN (??)',
    [$dop->map($posts, 'id')]
)->fetchAll();

// Find posts with more than 3 categorizations using a sub-query as a parameter
$catCount = $dop('SELECT COUNT(*) FROM categorization WHERE post_id = post.id');
$posts = $dop(
    'SELECT * FROM post WHERE (::catCount) >= 3',
    ['catCount' => $catCount]
)->fetchAll();
```

Internally, `??` and `::named` parameters are resolved before statement preparation.
Note that due to the current implementation using regular expressions,
you should *never* use quoted strings directly. Always use bound parameters.

## Reference

See [API.md](API.md) for a complete API reference.

## Contributors

- [jayaddison](https://github.com/jayaddison)

Thanks!
