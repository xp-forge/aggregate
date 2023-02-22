<?php namespace util\data\unittest;

use lang\IllegalArgumentException;
use test\{Assert, Expect, Test, TestCase, Values};
use util\Bytes;
use util\data\Aggregate;

class AggregateTest {
  private static $posts= [
    ['postId' => 1, 'authorId' => 1, 'text' => 'Post #1'],
    ['postId' => 2, 'authorId' => 1, 'text' => 'Post #2'],
    ['postId' => 3, 'authorId' => 2, 'text' => 'Post #3'],
  ];
  private static $comments= [
    ['commentId' => 1, 'postId' => 1, 'authorId' => 1, 'text' => 'Re: Post #1'],
    ['commentId' => 2, 'postId' => 3, 'authorId' => 1, 'text' => 'Re (1): Post #3'],
    ['commentId' => 3, 'postId' => 3, 'authorId' => 2, 'text' => 'Re (2): Post #3'],
  ];
  private static $likes= [
    ['objectId' => 1, 'kind' => 'comment', 'authorId' => 1],
    ['objectId' => 2, 'kind' => 'post', 'authorId' => 1],
    ['objectId' => 3, 'kind' => 'post', 'authorId' => 1],
    ['objectId' => 3, 'kind' => 'post', 'authorId' => 2],
  ];

  /** Selects posts with given author IDs */
  private static function postsFor(array $authorIds) {
    foreach (self::$posts as $post) {
      if (in_array($post['authorId'], $authorIds)) yield $post;
    }
  }

  /** Selects comments with given author IDs */
  private static function commentsFor(array $authorIds) {
    foreach (self::$comments as $comment) {
      if (in_array($comment['authorId'], $authorIds)) yield $comment;
    }
  }

  /** Selects comments with given author IDs */
  private static function likesFor(string $kind, array $objectIds) {
    foreach (self::$likes as $like) {
      if ($kind === $like['kind'] && in_array($like['objectId'], $objectIds)) yield $like;
    }
  }

  #[Test]
  public function can_create_from_iterator() {
    Aggregate::of(new Bytes('abc'));
  }

  #[Test]
  public function can_create_from_generator() {
    $f= function() { yield 1; yield 2; yield 3; };
    Aggregate::of($f());
  }

  #[Test]
  public function can_create_from_array() {
    Aggregate::of([]);
  }

  #[Test, Expect(IllegalArgumentException::class)]
  public function cannot_create_from_non_iterable() {
    Aggregate::of(null);
  }

  #[Test]
  public function is_iterable() {
    Assert::true(Aggregate::of([]) instanceof \Traversable);
  }

  #[Test, Values([[[]], [[['id' => 1]]], [[['id' => 1], ['id' => 2]]],])]
  public function without_collecting_returns($input) {
    Assert::equals($input, Aggregate::of($input)->all());
  }

  #[Test]
  public function collect() {
    $aggregate= Aggregate::of([['personId' => 1], ['personId' => 2]])
      ->collect('posts', ['personId' => 'authorId'], function($ids) { return self::postsFor($ids); })
      ->all()
    ;
    Assert::equals(
      [
        ['personId' => 1, 'posts' => [self::$posts[0], self::$posts[1]]],
        ['personId' => 2, 'posts' => [self::$posts[2]]],
      ],
      $aggregate
    );
  }

  #[Test]
  public function collect_on_empty() {
    $aggregate= Aggregate::of([])
      ->collect('posts', ['personId' => 'authorId'], function($ids) { return self::postsFor($ids); })
      ->all()
    ;
    Assert::equals([], $aggregate);
  }

  #[Test]
  public function collect_nested() {
    $aggregate= Aggregate::of([['personId' => 1], ['personId' => 2]])
      ->collect('posts', ['personId' => 'authorId'], function($ids) {
        return Aggregate::of(self::postsFor($ids))
          ->collect('comments', ['postId' => 'postId'], function($ids) { return self::commentsFor($ids); })
        ;
      })
      ->all()
    ;
    Assert::equals(
      [
        ['personId' => 1, 'posts' => [
          self::$posts[0] + ['comments' => [self::$comments[0]]],
          self::$posts[1] + ['comments' => []]
        ]],
        ['personId' => 2, 'posts' => [
          self::$posts[2] + ['comments' => [self::$comments[1], self::$comments[2]]],
        ]],
      ],
      $aggregate
    );
  }

  #[Test]
  public function collect_multiple() {
    $aggregate= Aggregate::of([['personId' => 1], ['personId' => 2]])
      ->collect('posts', ['personId' => 'authorId'], function($ids) {
        return Aggregate::of(self::postsFor($ids))
          ->collect('likes', ['postId' => 'objectId'], function($ids) { return self::likesFor('post', $ids); })
          ->collect('comments', ['postId' => 'postId'], function($ids) {
            return Aggregate::of(self::commentsFor($ids))
              ->collect('likes', ['commentId' => 'objectId'], function($ids) { return self::likesFor('comment', $ids); })
            ;
          })
        ;
      })
      ->all()
    ;
    Assert::equals(
      [
        ['personId' => 1, 'posts' => [
          self::$posts[0] + [
            'likes'    => [],
            'comments' => [self::$comments[0] + ['likes' => [self::$likes[0]]]],
          ],
          self::$posts[1] + [
            'likes'    => [self::$likes[1]],
            'comments' => [],
          ],
        ]],
        ['personId' => 2, 'posts' => [
          self::$posts[2] + [
            'likes'    => [self::$likes[2], self::$likes[3]],
            'comments' => [self::$comments[1] + ['likes' => []], self::$comments[2] + ['likes' => []]],
          ],
        ]],
      ],
      $aggregate
    );
  }

  #[Test]
  public function yields_empty_list_if_nothing_collected() {
    $aggregate= Aggregate::of([['personId' => 1], ['personId' => 3]])
      ->collect('posts', ['personId' => 'authorId'], function($ids) { return self::postsFor($ids); })
      ->all()
    ;
    Assert::equals(
      [
        ['personId' => 1, 'posts' => [self::$posts[0], self::$posts[1]]],
        ['personId' => 3, 'posts' => []],
      ],
      $aggregate
    );
  }
}