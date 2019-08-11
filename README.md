Aggregate data on lists
=======================

[![Build Status on TravisCI](https://secure.travis-ci.org/xp-forge/aggregate.svg)](http://travis-ci.org/xp-forge/aggregate)
[![XP Framework Module](https://raw.githubusercontent.com/xp-framework/web/master/static/xp-framework-badge.png)](https://github.com/xp-framework/core)
[![BSD Licence](https://raw.githubusercontent.com/xp-framework/web/master/static/licence-bsd.png)](https://github.com/xp-framework/core/blob/master/LICENCE.md)
[![Supports PHP 7.0+](https://raw.githubusercontent.com/xp-framework/web/master/static/php-7_0plus.png)](http://php.net/)
[![Latest Stable Version](https://poser.pugx.org/xp-forge/aggregate/version.png)](https://packagist.org/packages/xp-forge/aggregate)

This API allows working with data sequences of different kinds in a functional style, e.g. map/reduce.

Example
-------
The following shows how the *Aggregate* class works, firing only **two** SQL queries instead of creating an `n + 1`-problem.

```php
use util\data\Aggregate;
use rdbms\DriverManager;

$conn= DriverManager::getConnection($dsn);
$posts= Aggregate::of($conn->query('select * from post'))
  ->collect('comments', ['id' => 'post_id'], function($ids) use($conn) {
    return $conn->query('select * from comment where post_id in (%d)', $ids);
  })
  ->all()
;

// [
//   [
//     'id'       => 1,
//     'body'     => 'The first post',
//     'comments' => [
//        ['id' => 1, 'post_id' => 1, 'body' => 'Re #1: The first post'],
//        ['id' => 2, 'post_id' => 1, 'body' => 'Re #2: The first post'],
//     ]
//   ],
//   [
//     'id'       => 2,
//     'body'     => 'The second post',
//     'comments' => [
//        ['id' => 3, 'post_id' => 2, 'body' => 'Re #1: The second post'],
//     ]
//   ],
// ]
``` 