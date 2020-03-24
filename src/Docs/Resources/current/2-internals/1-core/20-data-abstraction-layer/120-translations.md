[titleEn]: <>(Translations)
[hash]: <>(article:dal_translations)

The data abstraction layer supports translations out of the box. In order to do so it uses `EntityTranslationDefinition` as an association.

### A translation definition?

Breaking these fields into different tables enables you to search and inherit data very easily.
It provides a strict structure and data consistency is ensured through constraints.
Given the following [DDL] for MySQL:

```sql
CREATE TABLE `product` (
    `id` BINARY(16) NOT NULL,
    `additional_text` VARCHAR(255),
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`)
);
```

It will be split up like this to provide the previously listed features:

```sql
CREATE TABLE `product` (
    `id` BINARY(16) NOT NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`)
);
CREATE TABLE `product_translation` (
    `product_id` BINARY(16) NOT NULL,
    `language_id` BINARY(16) NOT NULL,
    `additional_text` VARCHAR(255) NOT NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3),
    PRIMARY KEY (`product_id`, `language_id`),
    CONSTRAINT `fk.product_translation.language_id` FOREIGN KEY (`language_id`)
        REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.product_translation.product_id` FOREIGN KEY (`product_id`)
        REFERENCES `product` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
);
```


## Translate a definition

For the translation concept, you need two classes:

1. `EntityDefinition` for your entity, e.g. `ProductDefinition`
2. `EntityTranslationDefinition` which holds all translatable fields, e.g.
`ProductTranslationDefinition`

### Enable translations

Your entity definition must override the `getTranslationDefinitionClass()` method and provide a class reference to the translation definition, in this case `ProductTranslationDefinition::class`.

```php
public function getTranslationDefinitionClass(): ?string
{
    return ProductTranslationDefinition::class;
}
```

### Make fields translatable

If you already have fields in your entity, you have to copy them into the translation definition and replace them in the base definition with a custom field called `TranslatedField`. The only parameter is the `propertyName`, that maps it to the field in the translated definition.

**Before**

```php
class ProductDefinition
{
    new LongTextField('additional_text', 'additionalText')
}
```

**After**

```php
class ProductDefinition
{
    new TranslatedField('additionalText')
}

class ProductTranslationDefinition
{
    new LongTextField('additional_text', 'additionalText')
}
```

The `additionalText` field will now be written to the translation definition instead of the base definition. You don't have to handle this yourself as the DataAbstractionLayer knows about translatable fields.

Additionally, your entity translation definition must override the `getParentDefinitionClass` method.

```php
protected function getParentDefinitionClass(): string
{
    return ProductDefinition::class;
}
```


[DDL]: https://en.wikipedia.org/wiki/Data_definition_language
