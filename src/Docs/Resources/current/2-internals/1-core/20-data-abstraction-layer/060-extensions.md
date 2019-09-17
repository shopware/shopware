[titleEn]: <>(Extensions)

# Extensions

You can extend existing entities by creating a `EntityExtension`. The extension must implement the
`Shopware\Core\Framework\DataAbstractionLayer\EntityExtensionInterface` interface and has access to the fields within an entity. You can even manipulate the core fields
by adding flags and so on. When changing core fields, keep in mind that it can make your system behave inconsistently!

Extensions are used to add relations to existing entities. They are not intended to add normal fields like StringField,
BoolField or JsonField. Please use [CustomFields](./045-custom-field.md) in these cases.
## Creating an extension

Create a class which implements the `Shopware\Core\Framework\DataAbstractionLayer\EntityExtensionInterface` interface, implement the required methods and register and tag it in the service container as `shopware.entity.extension`.

### Method: getDefinitionClass()

You should return the definition the extension will be applied on, preferably a class reference.

### Method: extendFields()

The first parameter `$collection` is a collection of fields defined in the definition. You can now manipulate the
existing fields or add new by appending the collection.

The example below will add a new `1:n` relation to a new promotion entity.

```php
$fields->add(
    new OneToManyAssociationField('promotions', PromotionDefinition::class, 'product_id')
);
```

Given this example extends the `ProductDefinition`, the hydrator would run into a problem because of the
`promotions` property does not exist in the `ProductEntity`.

### Register extension in service container

```xml
<service id="SwagPromotion\Extension\ProductExtension">
    <tag name="shopware.entity.extension"/>
</service>
```

## Adding data to the entities

Please note the flag `Extension` in the example above.

Although you don't have access to the entity object itself, every entity comes with a key/value array in the
`extensions` property. The hydrator knows about the flag and therefore will write the data of this relation into
the `extensions` property with the key of this field `promotions`.

```php
$promotions = $product->getExtension('promotions');
```

You can then access your data by getting it from the entity.
