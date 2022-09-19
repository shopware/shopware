---
title: Add generics to collections
issue: NEXT-15740
---
# Core
* Changed `\Shopware\Core\Framework\Struct\Collection` to allow specifying the generic type of the collection and using that type as return type were applicable.
* Changed all classes extending from `\Shopware\Core\Framework\Struct\Collection` to specify the generic type of the collection.
* Added `\Shopware\Core\DevOps\StaticAnalyze\PHPStan\Type\CollectionHasSpecifyingExtension` to improve static analysis of collections.
___
# Upgrade information
## Generics for collections
`Collection` classes now support to specify the concrete type of the elements inside the collection via phpstan generics.
```php
/**
 * @extends Collection<DummyElement>
 */
class DummyCollection extends Collection
```

This will give you way better phpstan type coverage for the collections.
With the help of generics phpstan now also understands that if `count` on a collection and the return is greater than `0` that `first()` and `last()` will never return null.
```php
if ($collection->count() > 0) {
    // phpstan knows that $entity is never null
    $entity = $collection->first();
}

if (\count($collection) === 0) {
    return;
}
// phpstan knows that $entity is never null
$entity = $collection->first();

static::assertCount(2, $collection);
// phpstan knows that $entity is never null
$entity = $collection->first();
```

Similarly, phpstan knows that after you called `has()` with a specific key, that `get()` with the same key will never return null.
```php
if ($collection->has('foo')) {
    // phpstan knows that $entity is never null
    $entity = $collection->get('foo');
}

static::assertTrue($collection->has('foo'));
// phpstan knows that $entity is never null
$entity = $collection->get('foo');
```
