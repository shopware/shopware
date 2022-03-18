---
title: Refactor number ranges to be faster
issue: NEXT-20673
---
# Core
* Added abstract class `\Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\AbstractIncrementStorage`.
* Deprecated interface `\Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\IncrementStorageInterface` use `AbstractIncrementStorage` instead.
* Deprecated service-id `Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\IncrementStorageInterface`, use `Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\IncrementSqlStorage` instead.
* Added abstract class `\Shopware\Core\System\NumberRange\ValueGenerator\Pattern\AbstractValueGenerator`.
* Deprecated interface `\Shopware\Core\System\NumberRange\ValueGenerator\Pattern\ValueGeneratorPatternInterface` use `AbstractValueGenerator` instead.
* Added method `\Shopware\Core\System\NumberRange\ValueGenerator\Pattern\ValueGeneratorPatternRegistry::generatePattern()`.
* Deprecated method `\Shopware\Core\System\NumberRange\ValueGenerator\Pattern\ValueGeneratorPatternRegistry::getPatternResolver()`, please use `generatePattern()` directly.
* Deprecated all protected methods of `\Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGenerator`, those will be internal, please only use the public methods.
___
# Next Major Version Changes
## Refactoring of Number Ranges

We refactored the number range handling, to be faster and allow different storages to be used.
### Removal of `IncrementStorageInterface`

We removed the `Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\IncrementStorageInterface`.
If you have implemented a custom increment storage please use the abstract class `Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\AbstractIncrementStorage`.
Before:
```php
class CustomIncrementStorage implements IncrementStorageInterface
{
    public function pullState(\Shopware\Core\System\NumberRange\NumberRangeEntity $configuration): string
    {
        return $this->increment($configuration->getId(), $configuration->getPattern());
    }
    
    public function getNext(\Shopware\Core\System\NumberRange\NumberRangeEntity $configuration): string
    {
        return $this->get($configuration->getId(), $configuration->getPattern());
    }
}
```
After:
```php
class CustomIncrementStorage extends AbstractIncrementStorage
{
    public function reserve(array $config): string
    {
        return $this->increment($config['id'], $config['pattern']);
    }
    
    public function preview(array $config): string
    {
        return $this->get($config['id'], $config['pattern']);
    }
    
    public function getDecorated(): self
    {
        return $this->decorated;
    }
}
```
### Removal of `ValueGeneratorPatternInterface`

We removed the `Shopware\Core\System\NumberRange\ValueGenerator\Pattern\ValueGeneratorPatternInterface`.
If you have implemented a custom value pattern please use the abstract class `Shopware\Core\System\NumberRange\ValueGenerator\Pattern\AbstractValueGenerator`.

```php
class CustomPattern implements ValueGeneratorPatternInterface
{
    public function resolve(NumberRangeEntity $configuration, ?array $args = null, ?bool $preview = false): string
    {
        return $this->createPattern($configuration->getId(), $configuration->getPattern());
    }
    
    public function getPatternId(): string
    {
        return 'custom';
    }
}
```
After:
```php
class CustomIncrementStorage extends AbstractValueGenerator
{
    public function generate(array $config, ?array $args = null, ?bool $preview = false): string
    {
        return $this->createPattern($config['id'], $config['pattern']);
    }
    
    public function getPatternId(): string
    {
        return 'custom';
    }
    
    public function getDecorated(): self
    {
        return $this->decorated;
    }
}
```
### Removal of `\Shopware\Core\System\NumberRange\ValueGenerator\Pattern\ValueGeneratorPatternRegistry::getPatternResolver()`

We removed the `ValueGeneratorPatternRegistry::getPatternResolver()` method, please call the `generatePattern()` method now directly.
Before:
```php
$patternResolver = $this->valueGeneratorPatternRegistry->getPatternResolver($pattern);
if ($patternResolver) {
    $generated .= $patternResolver->resolve($configuration, $patternArg, $preview);
} else {
    $generated .= $patternPart;
}
```
After:
```php
$generated .= $this->valueGeneratorPatternRegistry->generatePattern($pattern, $patternPart, $configuration, $patternArg, $preview);
```
