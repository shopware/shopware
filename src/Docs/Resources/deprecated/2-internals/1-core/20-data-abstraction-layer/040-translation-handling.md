[titleEn]: <>(Translation handling)
[hash]: <>(article:dal_translation_handling)

The Data abstraction layer supports internationalization of entities as a core concept and obeys the general rules of the [Shopware Core](./../20-internationalization.md). In order to support this, many fields are translatable and many entities have a language relation. There is some special handling present to support this.

## Context language

The context object contains the currently selected language. All reads an writes are performed in relation to it. 

## Reading translated data

Data that has been read always contains the translations specified by the context's language. Although the DAL supports special handling for translations, they are a related entity and therefore can always be read raw as an association.

## Writing multiple languages at once

You can either write the fields on the base definition directly or use the `translations` property when writing.

### Context dependant

If you are writing the fields directly, the language of the current context will be used:

```php
// given $context will contain en-GB as language

$repository->create(
    [
        'stock' => 10,
        'description' => 'This is an example',
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
            'en-GB' => [
                'description' => 'This is an example',
            ],
        ]
    ],
    $context
);
```

### Non-context dependant

The `translations` field requires the language's UUID or locale code as index. Its values will then be mapped into the translation definition as seen above.

If you want to write multiple languages once, you can add more records to the `translations` field.

```php
$repository->create(
    [
        'stock' => 10,
        'translations' => [
            'de-DE' => [
                'description' => 'Das ist eine Beschreibung',
            ],
            'en-GB' => [
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

In the case mentioned above, the language in the context will be ignored as it is already specific enough and does not need any further mapping.
