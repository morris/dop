# Dop

[ ![Build Status](https://travis-ci.org/morris/dop.svg?branch=master) ](https://travis-ci.org/morris/dop)
[ ![Test Coverage](https://codeclimate.com/github/morris/dop/badges/coverage.svg) ](https://codeclimate.com/github/morris/dop/coverage)

Dop simplifies writing and executing complex SQL statements using
an immutable API on top of [PDO](http://php.net/manual/en/book.pdo.php).


## Usage

```php
// Connect to a database
$pdo = new PDO( 'sqlite:blog.sqlite3' );
$dop = new Dop\Connection( $pdo );

// Get some posts
$posts = $dop->query( 'post' )->where( 'is_published = ?', [ 1 ] )->exec();

// Get categorizations
$categorizations = $dop(
  'select * from categorization where post_id in ( ?? )',
  [ $posts->map( 'id' ) ]
)->exec();

// Find posts with more than 3 categorizations
$catCount = $dop( 'select count( * ) from categorization where post_id = post.id' );
$posts = $dop( 'select * from post where ( ::catCount ) >= 3',
  [ 'catCount' => $catCount ] )->exec();
```

__See [API.md](API.md) for a complete API reference.__


## Parameters

Dop introduces additional parameter markers written as `::name` or `??`.
They resolve to arbitrary values like arrays, `null`, and other SQL fragments,
and enable powerful composition:

```php
$authorIds = array( 1, 2, 3 );
$orderByTitle = $dop( 'order by title asc' );
$posts = $dop( 'select id from post where author_id in ( ?? ) ?? limit ??',
  array( $authorIds, $orderByTitle, 5 ) );

// use $posts as sub query
$cats = $dop( 'select * from categorization where post_id in ( ::posts )',
  array( 'posts' => $posts ) )->exec();
```

Internally, these parameters are resolved before statement preparation.
Note that due to the current implementation using regular expressions,
you should *never* insert quoted strings manually. Always use bound parameters.


## Installation

Dop requires PHP >= 5.3.0 and PDO.
Install via [composer](https://getcomposer.org/):

```
$ composer require morris/dop
```
