[titleEn]: <>(Translations)

The data abstraction layer supports translations out of the box. In order to do so it uses `EntityTranslationDefinition` as an association.

### A translation definition?

Breaking these fields into different tables enables you to search and inherit data very easily. It provides a strict structure and data consistency is ensured through constraints.

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


