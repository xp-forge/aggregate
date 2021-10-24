<?php namespace util\data;

use IteratorAggregate, Traversable;
use lang\IllegalArgumentException;

class Aggregate implements IteratorAggregate {
  private $elements, $aggregate= [];

  /**
   * Creates a new instance
   *
   * @param  iterable $elements The collection
   * @return self
   * @throws lang.IllegalArgumentException
   */
  public static function of($elements): self {
    if ($elements instanceof \Traversable || is_array($elements)) {
      $self= new self();
      $self->elements= $elements;
      return $self;
    }
    throw new IllegalArgumentException('Expected an iterable type');
  }

  /**
   * Aggregates elements by a lookup, which gets called with the IDs
   *
   * @param  string $name The key under which the collected elements will be placed
   * @param  [:string] $map A map mapping collection IDs to aggregate IDs
   * @param  function(var[]): iterable The lookup function 
   * @return self This instance
   */
  public function collect(string $name, array $map, callable $func): self {
    $this->aggregate[$name]= [key($map), $func, current($map)];
    return $this;
  }

  /** Iterates over elements in this aggregation */
  public function getIterator(): Traversable {
    $yield= [];

    // Gather all IDs in unique list
    $ids= [];
    foreach ($this->elements as $element) {
      foreach ($this->aggregate as $name => $aggregate) {
        $ids[$name][$element[$aggregate[0]]]= null;
      }
      $yield[]= $element;
    }

    // Compile maps for aggregated elements
    $map= [];
    foreach ($this->aggregate as $name => $aggregate) {
      if (isset($ids[$name])) foreach ($aggregate[1](array_keys($ids[$name])) as $object) {
        $map[$name][$object[$aggregate[2]]][]= $object;
      }
    }

    // Aggregate
    foreach ($yield as $element) {
      foreach ($this->aggregate as $name => $aggregate) {
        $element[$name]= $map[$name][$element[$aggregate[0]]] ?? [];
      }
      yield $element;
    }
  }

  /** @return var[] */
  public function all() {
    return iterator_to_array($this->getIterator());
  }
}