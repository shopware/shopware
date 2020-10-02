[titleEn]: <>(Step 4: Creating an entity)
[hash]: <>(article:bundle_entity)

While it's good to have the database tables running already, Shopware 6 does not know your new table yet.

Introducing the table to Shopware 6 is done by adding a so called `EntityDefinition` for your table.
As the name suggests, it defines your own entity, including its fields and name, the latter also represents the table name and therefore
has to perfectly match.
While it's up to you where to place your custom definition, we would recommend to stick to the [Core structure](./../../60-references-internals/10-core/__categoryInfo.md).
Thus, a good location for it would be in a directory like this: `<plugin root>/src/Core/Content/Bundle`
Create a new file called `BundleDefinition.php` in there.

Your own definition has to extend from the class `Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition`, which, once again,
enforces you to implement two methods: `getEntityName` and `defineFields`

`getEntityName`: Return a string equal to your table name. In this example it is `swag_bundle`.
`defineFields`: This method contains all the fields, that your entity or table consists of.
You've got an id field, a `discount_type` field and a `discount` field.
The other two columns `created_at` and `updated_at` don't have to be defined here, they're included by default.
You're asked to return a `Shopware\Core\Framework\DataAbstractionLayer\FieldCollection` instance here, which then has to contain an array of your fields.
There's several field classes, e.g. an `Shopware\Core\Framework\DataAbstractionLayer\Field\IdField` or a `Shopware\Core\Framework\DataAbstractionLayer\Field\StringField`, which you have to create
and pass into the `FieldCollection`.

This is how your BundleDefinition should look like now:
```php
<?php declare(strict_types=1);

namespace Swag\BundleExample\Core\Content\Bundle;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class BundleDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'swag_bundle';
    
    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new StringField('discount_type', 'discountType'))->addFlags(new Required()),
            (new FloatField('discount', 'discount'))->addFlags(new Required()),
        ]);
    }
}
```

The `getEntityName` method should be self explaining here, so let's have a look at the `defineFields` instead.
As explained earlier and as the return type suggests, you're supposed to return a new `FieldCollection` here.

Its constructor requires you to pass an array, which is meant to contain all Fields, which are used in your entity.
The first item in this array is an `IdField`. Most fields ask for two parameters, such as the `IdField`:
- A storage name, which represents the name of the field in the storage, e.g. the column in an SQL database.
- A property name, which defines how you can access this field later on. Make sure to remember those for the next step.

The `storageName` is written in snake_case, while the `propertyName` must be written in `lowerCamelCase`.

Additionally, two flags are added to the `IdField`.
But what is a flag in the first place? One could describe them as 'additional information' of a field, such as defining a field
as required or setting it as a primary key.
And that's also the two flags, that were used on this field: `Required` and `PrimaryKey`
The `Required` flag lets the data abstraction layer know, that this field is not optional and must always be set.
Using the `PrimaryKey` flag is important, so the data abstraction layer knows which field to use when working with associations and foreign keys.

The next two items are a `StringField` for the discount type and a `FloatField` for the actual discount, that will be applied to this bundle.
Both are defined as `Required`, so you can make sure those values are always set and you don't have to take care
of any kind of default value. Also, a bundle without a discount would make no sense.

## Setting up the entity and collection

When fetching for your bundle data now, you'd get an `EntityCollection` of `Entity` classes now,
each one representing a configured bundle. Both the `EntityCollection` as well as the `Entity` class are generic classes,
thus not providing any auto-completion while developing. If you create your own `EntityCollection` and `Entity` instead,
you'll have auto-completion for your custom entity.
This will come in handy later in this tutorial and in general when developing your plugin.

Let's start with a custom `Entity` class, a good name for it would be `BundleEntity`.
Simply create this class in the same directory like your `BundleDefinition`. Your `BundleEntity` class has to extend from `Shopware\Core\Framework\DataAbstractionLayer\Entity`.

Your entity should now contain every field, that you already defined in the `EntityDefinition`.
Each field has to come with a getter and a setter.

The `id` property can and should be integrated by using the trait `Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait` in your class.
It adds an `$id` property plus the respective getter and setter methods.
Now add the other two fields `discountType` and `discount` to your entity as well. Just make sure that you use the same names for the properties you already specified in the definition.
Once more, do not add the `createdAt` and `updatedAt` fields here, they are automatically added by extending from the `Entity` class.

This is how your `BundleEntity` should look like now:
```php
<?php declare(strict_types=1);

namespace Swag\BundleExample\Core\Content\Bundle;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class BundleEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $discountType;

    /**
     * @var float
     */
    protected $discount;

    public function getDiscountType(): string
    {
        return $this->discountType;
    }

    public function setDiscountType(string $discountType): void
    {
        $this->discountType = $discountType;
    }

    public function getDiscount(): float
    {
        return $this->discount;
    }

    public function setDiscount(float $discount): void
    {
        $this->discount = $discount;
    }
}
```

Your entity is not used yet though, since your definition does not know your new class.
The `EntityDefinition` class provides a method called `getEntityClass` and returns the fully qualified class name of the `Entity` class to be used.
Override this method to return your custom entity's class name now:

```php
class BundleDefinition extends EntityDefinition
{
    ...
    public function getEntityClass(): string
    {
        return BundleEntity::class;
    }

    protected function defineFields(): FieldCollection
    ...
}
```

Your `BundleEntity` is now used by your `BundleDefinition`, so the `EntityCollection` will be a set of `BundleEntity` classes now instead of generic `Entity` classes.
Time to make sure you're also using a custom `EntityCollection`, which works the very same way like setting a custom entity.
There's another method called `getCollectionClass`, which can be overridden in your `BundleDefinition` as well, so also add this:

```php
class BundleDefinition extends EntityDefinition
{
    ...
    public function getCollectionClass(): string
    {
        return BundleCollection::class;
    }

    protected function defineFields(): FieldCollection
    ...
}
```

And now create the `BundleCollection` class in the same directory as your `BundleDefinition` and `BundleEntity`.
Extending from `Shopware\Core\Framework\DataAbstractionLayer\EntityCollection`, it comes with a method called `getExpectedClass`, which once again returns
the fully qualified class name of the entity class to be used.
Also override this method and return your `BundleEntity` here.
Additionally you **can** provide helper methods in your custom `EntityCollection`, such as filtering the result set by certain conditions.

This is how your collection class would then look like:
```php
<?php declare(strict_types=1);

namespace Swag\BundleExample\Core\Content\Bundle;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void              add(BundleEntity $entity)
 * @method void              set(string $key, BundleEntity $entity)
 * @method BundleEntity[]    getIterator()
 * @method BundleEntity[]    getElements()
 * @method BundleEntity|null get(string $key)
 * @method BundleEntity|null first()
 * @method BundleEntity|null last()
 */
class BundleCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return BundleEntity::class;
    }
}
```

The class documentation is just another helper to have a proper auto-completion when working with your `BundleCollection`.

## Registering the custom definition

You've got a `BundleDefinition`, which also knows your `BundleCollection` as well as the `BundleEntity`, so everything is perfectly set up.

**Just one more thing is missing:**
Shopware 6 does not know your `BundleDefinition` yet!

Your custom definition has to be defined as a tagged service, using the `shopware.entity.definition` tag.
Registering a service to Shopware 6 works just like in Symfony itself, it has to be registered to the DI container
using a configuration file. What an DI container is and how it works, **won't** be explained as part of this tutorial.
Head over to the [Symfony documentation](https://symfony.com/doc/current/service_container.html) to figure out more about the DI container and
service registration in Symfony.

Shopware 6 is looking for a `services.xml` file in your plugin automatically, but you need to place it into the proper directory.
By default it is expected to be in the following location relative to your plugin's base class: `Resources/config/services.xml`
In this example, the full path then would be `<plugin root>/src/Resources/config/services.xml`, so go ahead and create this file.

The structure of a Symfony configuration file is also not explained here, make sure to have a look at the [Symfony documentation](https://symfony.com/doc/current/service_container.html).
In order to maybe use auto wiring, we mostly use a class' FQCN as service IDs, so you immediately know which class to expect from a given service.

As already mentioned, your `BundleDefinition` has to be registered using the `shopware.entity.definition` tag, because Shopware 6
is looking for definitions by looking for this tag.

Here's the `services.xml` as it should look like now:
```xml
<?xml version="1.0" ?>

<container xmlns="http://symfony.com/schema/dic/services"
           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
           xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">
    
    <services>
        <service id="Swag\BundleExample\Core\Content\Bundle\BundleDefinition">
            <tag name="shopware.entity.definition" entity="swag_bundle" />
        </service>
    </services>
</container>
```

Please have a look at the `<tag>` element. Not only does it have the mentioned `shopware.entity.definition` name, but it also comes with
an `entity` attribute. Make sure to always provide this attribute when using this tag, otherwise you'll see an error at runtime.
Its value has to be equal to the name you've used in the `EntityDefinition` itself, `swag_bundle` in this case.

**And that's it, your definition is now completely registered to Shopware 6! From here on
your custom entity is accessible throughout the API.**

Now continue with the [next step](./050-entity-association.md) to add the product association to your entity.
