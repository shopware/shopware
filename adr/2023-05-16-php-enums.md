---
title: Use PHP 8.1 Enums
date: 2023-05-16
area: core
tags: [php, '8.1', enum]
---

## Context

As of Shopware 6.5 the minimum version of PHP is 8.1. We would like to promote the usage of PHP Enums.

Enums are useful where we have a predefined list of constant values. It's now not necessary to provide values as constants, and it's not necessary to create arrays of the constants to check validity.

## Decision

All new code which needs to represent a collection of constant values should now use Enums.

A few examples might be:

* Product Types (Parent, Variant)
* Product Status (Enabled, Disabled)
* Backup Type (Full,  Incremental)

Where possible, we should migrate existing constant lists to use Enums. See the following Migration Strategy:

## Backwards Compatibility / Migration Strategy

To migrate a list of constant values, where an API accepts a "type" parameter which should exist in the list of constant values we can use the [Expand & Contract pattern](https://www.tim-wellhausen.de/papers/ExpandAndContract/ExpandAndContract.html) to migrate in a backwards compatible manner:

Consider the following example:

```php
class Indexer
{
    public const PARTIAL = 'partial';
    public const FULL = 'full';

    public function product(int $id, string $method): void
    {
        if (!in_array($method, [self::PARTIAL, self::FULL], true)) {
            throw new \InvalidArgumentException();
        }
    
        match ($method) {
            self::PARTIAL => $this->partial($id),
            self::FULL => $this->full($id)
        };
    }
}
```

Step 1: Create the ENUM & Accept in API as well as string. For this step it is necessary to maintain the allowed values in both the constants and the ENUM:

Note: In PHP 8.1 we cannot assign directly an ENUM to a constant. For the future, it is worth noting that this is supported in PHP 8.2 with backed ENUMS: `public const PARTIAL = IndexMethod::PARTIAL->value;`

```php
enum IndexMethod
{
    case PARTIAL;
    case FULL;
}

class Indexer
{ 
    public function product(int $id, IndexMethod|string $method): void
    {
       ...
    }
}
```

Step 2: Create ENUM from primitive type if string value passed:

Note: If your ENUM is backed with a value you can use `BackedEnum::from` to perform automatic casting and validation. Otherwise you will need to map the values manually.

```php
class Indexer
{
    public const PARTIAL = 'partial';
    public const FULL = 'full';

    public function product(int $id, IndexMethod|string $method): void
    {
        if (is_string($method)) {
            $method = match ($method) {
                'partial' => IndexMethod::PARTIAL,  
                'full' => IndexMethod::FULL,
                default => throw new \InvalidArgumentException()
            };
        }

        match ($method) {
            IndexMethod::PARTIAL => $this->partial($id),
            IndexMethod::FULL => $this->full($id)
        };
    }
}
```

Step 3: Deprecate the constants and passing primitive values in the method:

```php
class Indexer
{
    // @deprecated tag:v6.6.0 - Constant will be removed, use enum IndexMethod::PARTIAL
    public const PARTIAL = 'partial';
    // @deprecated tag:v6.6.0 - Constant will be removed, use enum IndexMethod::FULL
    public const FULL = 'full';

    /**
     * @deprecated tag:v6.6.0 - Parameter $method will not accept a primitive in v6.6.0
     */
    public function product(int $id, IndexMethod|string $method): void
    {
        if (is_string($method)) {
            $method = match ($method) {
                'partial' => IndexMethod::PARTIAL,  
                'full' => IndexMethod::FULL,
                default => throw new \InvalidArgumentException()
            };
        }

        match ($method) {
            IndexMethod::PARTIAL => $this->partial($id),
            IndexMethod::FULL => $this->full($id)
        };
    }
}
```

Step 4: Remove deprecations in next major.


Which leaves us with the following, succinct code:

```php
enum IndexMethod
{
    case PARTIAL;
    case FULL;
}

class Indexer
{
    public function product(int $id, IndexMethod $method): void
    {
        match ($method) {
            IndexMethod::PARTIAL => $this->partial($id),
            IndexMethod::FULL => $this->full($id)
        };
    }
}

(new Indexer())->product(1, IndexMethod::PARTIAL);
```
