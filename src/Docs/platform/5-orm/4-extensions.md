# Extensions

You can extend existing entities by creating an `EntityExtension`. The extension must implement the
`Shopware\Core\Framework\ORM\EntityExtensionInterface` interface and has access to the fields within an entity. You can even manipulate the core fields
by adding flags and so on. When changing core fields, keep in mind that it can make you system behave inconsistent!

## Creating an extension

Create a class which extends the `Shopware\Core\Framework\ORM\EntityExtensionInterface` interface, implement
the required methods and register and tag it in the service container as `shopware.entity.extension`.

### Method: getDefinitionClass()

This method does not take any parameters.

You should return the definition the extension will be applied on, preferably a class reference.

### Method: extendFields()

The first parameter `$fields` is a collection of fields defined in the definition. You can now manipulate the
existing fields or add new by appending the collection.

The example below will add a new `1:n` relation to a new promotion entity.

```php
$fields->add(
    (new OneToManyAssociationField('promotions', PromotionDefinition::class, 'product_id', true))->setFlags(new Extension())
);
```

Given this example extends the `ProductDefinition`, the hydrator would run into a problem because the
`promotions` property does not exist in the `ProductStruct`.

### Register extension in service container

```xml
<service class="SwagPromotion\Extension\ProductExtension" id="SwagPromotion\Extension\ProductExtension">
    <tag name="shopware.entity.extension"/>
</service>
```

## Adding data to the structs

Please note the flag `Extension` in the example above.

Because you don't have access to the struct itself, so every struct comes with a key/value array in the
`extensions` property. The hydrator knows about the flag and therefore will write the data of this relation into
the `extensions` property with the key of this field `promotions`.

```php
$promotions = $product->getExtension('promotions');
```

You can then access your data by getting it from the struct.
