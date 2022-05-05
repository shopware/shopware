---
title: Specify Translation overwrites in write payloads
issue: NEXT-12900
---
# Core
* Changed `\Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\TranslatedFieldSerializer` to not overwrite values specified direct under `translations`.
* Changed `\Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\TranslationsAssociationFieldSerializer` so that translation values with iso-codes take precedence over values with language ids.
___
# Upgrade Information
## Translation overwrite priority specified for write payloads

We specified the following rules for overwrites of translation values in write-payloads inside the DAL.
1. Translations indexed by `iso-code` take precedence over values indexed by `language-id`
2. Translations specified on the `translations`-association take precedence over values specified directly on the translated field.

For a more information on those rules refer to the [according ADR](../../adr/2022-03-29-specify-priority-of-translations-in-dal-write-payloads.md).

Let's take a look on some example payloads, to see what those rules mean.
**Note:** For all examples we assume that `en-GB` is the system language.

### Example 1
Payload:
```php
[
    'name' => 'default',
    'translations' => [
        'name' => [
            'en-GB' => 'en translation',
         ],
    ],
]
```
Result: `en translation`, because values in `translations` take precedence over those directly on the translated fields.
### Example 2
Payload:
```php
[
    'name' => 'default',
    'translations' => [
        'name' => [
            Defaults::SYSTEM_LANGUAGE => 'en translation',
         ],
    ],
]
```
Result: `en translation`, because of the same reasons as above.
### Example 3
Payload:
```php
[
    'name' => [
        Defaults::SYSTEM_LANGUAGE => 'id translation',
        'en-GB' => 'iso-code translation',
    ],
]
```
Result: `iso-code translation`, because `iso-code` take precedence over `language-id`.
### Example 4
Payload:
```php
[
    'name' => 'default',
    'translations' => [
        'name' => [
            Defaults::SYSTEM_LANGUAGE => 'id translation',
            'en-GB' => 'iso-code translation',
         ],
    ],
]
```
Result: `iso-code translation`, because `iso-code` take precedence over `language-id`.
### Example 5
Payload:
```php
[
    'name' => [
       Defaults::SYSTEM_LANGUAGE => 'default', 
    ],
    'translations' => [
        'name' => [
            Defaults::SYSTEM_LANGUAGE => 'en translation',
         ],
    ],
]
```
Result: `en translation`, because values in `translations` take precedence over those directly on the translated fields.
### Example 6
Payload:
```php
[
    'name' => [
       'en-GB' => 'default', 
    ],
    'translations' => [
        'name' => [
            Defaults::SYSTEM_LANGUAGE => 'en translation',
         ],
    ],
]
```
Result: `default`, because `iso-code` take precedence over `language-id`, and that rule has a higher priority then the second "association rule".
