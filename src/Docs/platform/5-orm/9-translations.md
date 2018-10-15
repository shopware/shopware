# Translations

Beside the ability to save primitive data to the database, it is possible 
to translate them in different languages.

The translation system is based on a two-level language system. This means,
that there is a first-level language, which is your base language like 
English (en_GB) or German (de_DE). The second level is a derivation of this
language like Switzergerman (`de_CH`).

So your data is saved in `de_DE` and may have a second-level translation 
`de_CH` for overriding a specific value.

**Example**

A product's name is "Schneebesen" but in a sales channel for Switzerland, 
you'll get "Schwingbesen" as that would be the correct translation. All other,
not especially translated field, will stick to the german language `de_DE`.

### Why a translation definition?

Breaking these fields into different tables enables you the search and inherit data very
easily. It provides a strict structure and data consistency is ensured through constraints.

## Translate a definition

For the translation concept, you need two classes:

1. `EntityDefinition` for your entity, e.g. `ProductDefinition`
2. `EntityDefinition` which holds all translatable fields, e.g.
`ProductTranslationDefinition`

### Enable translations

Your entity definition must override the `getTranslationDefinitionClass()`
method and provide a class reference to the translation definition, in this
case `ProductTranslationDefinition::class`.

```php
public static function getTranslationDefinitionClass(): ?string
{
    return ProductTranslationDefinition::class;
}
```

### Make fields translatable

If you already have fields in your entity, you have to copy them into the
translation definition and wrap them in the base definition a custom 
field called `TranslatedField`.

**Before**

```php
new LongTextField('description', 'description')
```

**After**

```php
new TranslatedField(new LongTextField('description', 'description'))
```

The `description` field will now be written to the translation definition
instead of the base definition. You don't have to handle this yourself 
as the ORM knows about translatable fields.

## Writing multiple languages at once

You can either write the fields on the base definition directly or use the
`translations` property when writing.

### Context dependant

If you are writing the fields directly, the language of the current context
will be used:

```php
// given $context will contain de_DE as language

$repository->create(
    [
        'stock' => 10,
        'description' => 'Das ist eine Beschreibung',
    ],
    $context
);
```

Internally, this will be mapped to the following payload:

```php
$repository->create(
    [
        'stock' => 10,
        'translations' => [
            'de_DE' => [
                'description' => 'Das ist eine Beschreibung',
            ],
        ]
    ],
    $context
);
```

### Non-context dependant

The `translations` field requires the language's UUID or locale code an index.
Its values will then be mapped into the translation definition as seen above.

If you want to write multiple language once, you can add more records to the
`translations` field.

```php
$repository->create(
    [
        'stock' => 10,
        'translations' => [
            'de_DE' => [
                'description' => 'Das ist eine Beschreibung',
            ],
            'en_GB' => [
                'description' => 'This is a description',
            ],
            '04ed51ccbb2341bc9b352d78e64213fb' => [
                'description' => 'Dat is een beschrijving',
            ],
        ]
    ],
    $context
);
```

In this case above, the language in the context will be ignored as it is
already specific enough and does not need any further mapping.
