[titleEn]: <>(Step 5: Adding associations to the entity)
[hash]: <>(article:bundle_association)

You were probably wondering how this bundle is going to work now, because your bundle database table is not related to any products yet.

So let's get that one going now.
A bundle consists of multiple products, which would mean we have one bundle to multiple products.
Hence: One bundle => N Products

A single product though can be assigned to multiple bundles.
Hence: One Product => M Bundles
Therefore one could also say: M Bundles are assigned to N Products

This constellation is mainly called a [ManyToMany](https://en.wikipedia.org/wiki/Many-to-many_(data_model)) association, which are handled using a "mapping table", often referred to as an "associative table".

So, let's start with this table, its only columns have to be `bundle_id`, `product_id` and `product_version_id`, so we can map bundles to products and vice versa.
Since a product's primary key consists of two columns, `id` and `version_id`, you have to add them both to your table as well.
You name the table `swag_bundle_product` then, so it's quite clear what it contains.

## Migration

In order to setup this, you can adjust your existing Migration file, so it also creates the
mapping table.
*Note: Do **not** change an existing Migration if your plugin is already in use by someone. In that case, create a new Migration instead!
This also means, that you have to re-install your plugin if you adjust the an Migration.*

```php
<?php declare(strict_types=1);

namespace Swag\BundleExample\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1554708925Bundle extends MigrationStep
{
    ...
    public function update(Connection $connection): void
    {
        ...

        $connection->executeUpdate('
            CREATE TABLE IF NOT EXISTS `swag_bundle_product` (
              `bundle_id` BINARY(16) NOT NULL,
              `product_id` BINARY(16) NOT NULL,
              `product_version_id` BINARY(16) NOT NULL,
              `created_at` DATETIME(3) NOT NULL,
              PRIMARY KEY (`bundle_id`, `product_id`, `product_version_id`),
              CONSTRAINT `fk.bundle_product.bundle_id` FOREIGN KEY (`bundle_id`)
                REFERENCES `swag_bundle` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.bundle_product.product_id__product_version_id` FOREIGN KEY (`product_id`, `product_version_id`)
                REFERENCES `product` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    ...
}
```

Basically there's nothing special about this query - the columns mentioned above plus a `created_at` column to save the date when a product
got assigned to a bundle.
Those rows will most likely never be updated, but rather deleted and newly created, so there's no reason to have an `updated_at` column here.
The last few lines only create the necessary foreign keys to the `swag_bundle` and the `product` table.
Since those constraints have to be unique, the following pattern is used: `fk.table_name.column_name`

## EntityDefinition

Just like your custom entity, you're required to set up a new `EntityDefinition` class for your new table.
This time it extends from the class `Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition` though. Mainly this is done
because an `EntityDefinition` for a mapping table does not need an own `Entity` or `Collection`, because its only purpose is linking other definitions together.
Therefore classes of the type `MappingEntityDefinition` are excluded from several processes in the DAL, such as from the definition validation.

Place it in a new directory called `Aggregate/BundleProduct` starting from the same directory you've placed your other definition in, so it's equal to the core.
Its path looks like this then: `<plugin root>/src/Core/Content/Bundle/Aggregate/BundleProduct/BundleProductDefinition.php`
The `MappingEntityDefinition` is basically an aggregation to the root entity, which serves no purpose without the root entity itself.

The `BundleProductDefinition` still has to implement the `getEntityName` as well as the `defineFields` methods. A `MappingEntityDefinition` does not come with any default fields,
so make sure to add the `CreatedAtField` manually this time by overriding the `defaultFields` method as well.
*Note: If you're a hundred percent sure you're not going to need the `created_at` value, it's fine to leave that part out.*

Here's the example for it:
```php
<?php declare(strict_types=1);

namespace Swag\BundleExample\Core\Content\Bundle\Aggregate\BundleProduct;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Swag\BundleExample\Core\Content\Bundle\BundleDefinition;

class BundleProductDefinition extends MappingEntityDefinition
{
    public function getEntityName(): string
    {
        return 'swag_bundle_product';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField('bundle_id', 'bundleId', BundleDefinition::class))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('product_id', 'productId', ProductDefinition::class))->addFlags(new PrimaryKey(), new Required()),
            (new ReferenceVersionField(ProductDefinition::class))->addFlags(new PrimaryKey(), new Required()),
            new ManyToOneAssociationField('bundle', 'bundle_id', BundleDefinition::class),
            new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class),
            new CreatedAtField()
        ]);
    }
}
```

The method `getEntityName` returns the name to the table again, `swag_bundle_product` that is. Now let's switch the focus to the `defineFields` method.

First of all you're defining the actual fields themselves: the `bundle_id` and the `product_id` field.
They're both just pointing to another table, so use the `Shopware\Core\Framework\DataAbstractionLayer\Field\FkField` here.
The `FkField` needs the same two parameters `storageName` and `propertyName` like other fields, but also has a third required parameter `referenceClass`, which
must be a string pointing to the respective `EntityDefinition` class.
The `product_version_id` is a special kind of field and is handled by a `ReferenceVersionField` so keep that in mind whenever 
working with entities with an version field.
The `ReferenceVersionField` automatically determines the `storageName` and `propertyName`, so you only have to refer to the `EntityDefinition` class here.

The last two fields are the actual associations to the respective definition classes. This is done once for each column, that is required to be associated,
in this case `product_id` and `bundle_id`.
You might wonder, why you need to define them both as a `FkField` as well as an `ManyToOneAssociationField`, right?
That's mainly because the first two fields define how the field in the database looks and nothing more, while the latter two define the actual association
and how it's gonna be accessed later on.
In your entity you also have two properties for each association:
- `entity_id`, which is of the type `string`
- `entity`, which contains the actual instance of the entity, `BundleEntity` in this case

This way you could use both `$bundle->getProductIds()` as well as `$bundle->getProducts()` in order to either get an array of IDs
or a collection of actual entities.

## Adding product to BundleEntity and BundleDefinition

A single association always connects **two** entities, yet you've only defined the association in one definition, which is your `BundleProductDefinition`.
You have to add those associations to the connected definitions themselves, `BundleDefinition` and `ProductDefinition` that is.

### Adjusting the BundleDefinition

Adding those required changes to your own definition is a bit straight forward, since you own the code, so let's start with this. 

Open up your `BundleDefinition` class and add a new `ManyToManyField` to the `FieldCollection`.
Since this is not an actual field in the respective database table `swag_bundle`, but an association, there's no need for a `storageName` this time.
Instead the first parameter represents the `propertyName`, thus the name for the property in which the associated data will be stored.
It will contain the products associated to a bundle, so just call it `products`.
The next four parameters are basically references to the other definitions and their ID fields.

This is how the new field should look like then:
```php
protected function defineFields(): FieldCollection
{
    return new FieldCollection([
        ...
        new ManyToManyAssociationField('products', ProductDefinition::class, BundleProductDefinition::class, 'bundle_id', 'product_id'),
    ]);
}
```

Make sure to import the classes `ManyToManyAssociationField`, `ProductDefinition` and `BundleProductDefinition` into your `BundleDefinition`.

### Adjusting the BundleEntity

Since you've got a new property now, you also need to adjust your `BundleEntity` to know this new property.
That's quite simple, just add a new property with the same name like the string you just provided for the `propertyName`, so that's `$products`.
Do not forget to add the getter and setter for the new property as well.

```php
<?php declare(strict_types=1);

...
use Shopware\Core\Content\Product\ProductCollection;

class BundleEntity extends Entity
{
    ...
    
    /**
     * @var ProductCollection|null
     */
    protected $products;

    ...

    public function getProducts(): ?ProductCollection
    {
        return $this->products;
    }

    public function setProducts(ProductCollection $products): void
    {
        $this->products = $products;
    }
}
```

## Extending the ProductDefinition

Now it's time to add the new association to the `ProductDefinition`. This is done by writing an `EntityExtension` in your plugin.
Extensions should be placed into the same directory structure like the extended entity itself.
In this case this would be `Core/Content/Product` starting from your plugin's base class' directory.
Create a new file called `ProductExtension.php` in there and have the class extend the abstract `Shopware\Core\Framework\DataAbstractionLayer\EntityExtension`.  

This abstract class will automatically force you to implement `getDefinitionClass`.
Simply return the FQCN to the extended definition here.

Additionally you can implement the `extendFields` method, which provides you with a `FieldCollection` parameter.
Add the `ManyToManyAssociationField` to the `FieldCollection`, quite similar to the same field in your `BundleDefinition`, of course with adjusted parameters to be passed to the constructor.
Also note that you have to mark the association as an Inherited field, otherwise it would not work for variant products.

```php
<?php declare(strict_types=1);

namespace Swag\BundleExample\Core\Content\Product;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Swag\BundleExample\Core\Content\Bundle\Aggregate\BundleProduct\BundleProductDefinition;
use Swag\BundleExample\Core\Content\Bundle\BundleDefinition;

class ProductExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new ManyToManyAssociationField(
                'bundles', 
                BundleDefinition::class,
                BundleProductDefinition::class, 
                'product_id', 
                'bundle_id'
            ))->addFlags(new Inherited())
        );
    }

    public function getDefinitionClass(): string
    {
        return ProductDefinition::class;
    }
}
```

For every inherited field you have to add a binary column to the entity, which is used for saving the inherited information in a read optimized manner.
You can use the `InheritanceUpdaterTrait` for this purpose, so add the following lines to your migration:

```php
<?php declare(strict_types=1);

namespace Swag\BundleExample\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\InheritanceUpdaterTrait;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1554708925Bundle extends MigrationStep
{
    use InheritanceUpdaterTrait;

    public function update(Connection $connection): void
    {
        ...

        $this->updateInheritance($connection, 'product', 'bundles');
    }

    ...
}
```

The newly added column will be automatically managed by te DAL through an Indexer. But as there may already be some products in the Database that don't have that column set we have to run the `InheritanceIndexer` during the activation process of the plugin.
Because running the Indexer may take a longer time it's a bad idea to run the Indexer directly, therefore you can use the  `IndexerMessageSender` to run the Indexer asynchronously in your plugin base class `activate()`-method.

```php
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;

class BundleExample extends Plugin
{
    public function activate(ActivateContext $activateContext): void
    {
        $registry = $this->container->get(EntityIndexerRegistry::class);
        $registry->sendIndexingMessage(['product.indexer']);
    }
}
```

## Registering both services

Since the last time you've adjusted the `services.xml` file, you created two new classes that need to be registered in the DI container again:
`BundleProductDefinition` and the `ProductExtension`.

The first is registered like the `BundleDefinition` that you've already registered, using the same tag again.
Just make sure to provide the correct entity name via the entity attribute.

The `ProductExtension` is simply registered using a tag named `shopware.entity.extension`.

Here's your new `services.xml`:

```xml
<?xml version="1.0" ?>

<container xmlns="http://symfony.com/schema/dic/services"
           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
           xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">
    
    <services>
        <service id="Swag\BundleExample\Core\Content\Product\ProductExtension">
            <tag name="shopware.entity.extension"/>
        </service>

        <service id="Swag\BundleExample\Core\Content\Bundle\BundleDefinition">
            <tag name="shopware.entity.definition" entity="swag_bundle" />
        </service>

        <service id="Swag\BundleExample\Core\Content\Bundle\Aggregate\BundleProduct\BundleProductDefinition">
            <tag name="shopware.entity.definition" entity="swag_bundle_product"/>
        </service>
    </services>
</container>
```

In the [next step](./060-entity-translation.md) you will learn how to add translations for your entity.
