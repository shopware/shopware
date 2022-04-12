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
* Added `\Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\IncrementRedisStorage` to be able to generate number range increments using redis.
* Added config option `shopware.number_range.increment_storage` to specify which storage engine should be used to store the increment states.
* Added config option `shopware.number_range.redis_url` to specify the redis connection that should be used for the number ranges.
* Added `\Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\IncrementStorageRegistry` to get the configured increment store, and migrate data between stores.
* Added `number-range:migrate` console command to migrate data between storage engines per CLI.
___
# Administration
* Changed `sw-settings-number-range-detail` to not fetch the current number range state always from the database, but rather use the preview functionality to get the current state.
* Deprecated computed properties `numberRangeStateRepository` and `numberRangeStateCriteria` from component `sw-settings-number-range-detail` as those are not used anymore. Please use the `numberRangeService` to get the current state instead.
___
# Upgrade Information
## Redis store for number range increments
You can now generate the number range increments using redis instead of the Database.
In your `shopware.yaml` specify that you want to use the redis storage and the url that should be used to connect to the redis server to activate this feature:
```yaml
shopware:
  number_range:
    increment_storage: "Redis"
    redis_url: "redis://redis-host:port/dbIndex"
```

To migrate the increment data that is currently stored in the Database you can run the following CLI-command:
```shell
bin/console number-range:migrate SQL Redis
```
This command will migrate the current state in the `SQL` storage to the `Redis` storage.
**Note:** When running this command under load it may lead to the same number range increment being generated twice.
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
