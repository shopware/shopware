# Decorator pattern

The decorator pattern is a design pattern that allows behavior to be added to an individual object, either statically or dynamically, without affecting the behavior of other objects from the same class. 

## When to use the decorator pattern

You should choose the decorator pattern, when you want that other developers can extend your functionality. The most common use case is that other developers should be allowed to decorate or rewrite your DI container services.

https://symfony.com/doc/current/service_container/service_decoration.html

## How to use the decorator pattern

Instead of interfaces, we use abstract classes to define the base functionality of a service. This allows us to add more functions without breaking existing code. This decision was made in this [ADR](https://github.com/shopware/shopware/blob/trunk/adr/2020-11-25-decoration-pattern.md).

## Rules for the decorator pattern

When defining a service, which should be decorated, you have to follow these rules: 
- The abstract class has to implement a `getDecorated()` function which returns the abstract class.
- The core service has to throw a `DecorationPatternException` if the `getDecorated()` function is called.
- The abstract class **can not** be marked as `@internal` or `@final`
- An implementation of the abstract class **can not** provide any other public functions than the ones defined in the abstract class.
- Implementations of the abstract class **can not** act as an event subscriber, symfony event system **can not** handle this correctly.

These rules are enforced by the `\Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\DecorationPatternRule` class.

## Example
```php
abstract class AbstractRuleLoader
{
    abstract public function getDecorated(): AbstractRuleLoader;

    abstract public function load(Context $context): RuleCollection;
}

class CoreRuleLoader
{
    public function getDecorated(): AbstractRuleLoader {
        throw new DecorationPatternException(self::class);
    }
    
    public function load(Context $context): RuleCollection {
        // do some stuff 
    }
}

class SomePlugin extends AbstractRuleLoader
{
    public function __construct(private AbstractRuleLoader $inner) {}
    
    public function getDecorated(): AbstractRuleLoader {
        return $this->inner;
    }
    
    public function load(Context $context): RuleCollection {
        $rules = $this->inner->load($context);
        // add some data or execute some logic
        return $rules;
    }
}
```

When you add a new functionality to such a service, you have to add it to the abstract class but not as abstract function. This allows you to add new functions without breaking existing code.

```php
abstract class AbstractRuleLoader
{
    abstract public function getDecorated(): AbstractRuleLoader;

    abstract public function load(Context $context): RuleCollection;

    // introduced with shopware/platform v6.6
    public function create(Context $context): RuleCollection 
    {
        return $this->getDecorated()->create($context);
    }
}
```

## Alternative

Sometimes you want to decorate your own service but don't want to allow other developers to do it.

This can be the case when you "just" want to implement a logging or cache layer around your service or when you have to adjust something in our cloud product.

In this case, you should not use the decorator pattern described above and only inject the inner service and delegate the calls to it.

In this case you should mark the service as follows:
- if this is private api and should not be used by other developers, mark all classes as `@internal`
- if you want that developers can call public functions of your service but should not extend it, mark all classes as `@final`

```php
abstract class AbstractRuleLoader
{
    abstract public function load(Context $context): RuleCollection;
}

/**
 * @final - if you want that developers can use your service
 */
class CachedLoader extends AbstractRuleLoader
{
    public function __construct(
        private readonly AbstractRuleLoader $decorated,
        private readonly CacheInterface $cache
    ) {
    }

    public function load(Context $context): RuleCollection {
        return $this->cache->get(
            self::CACHE_KEY, 
            fn (): RuleCollection => $this->decorated->load($context)
        );
    }
}
```
