[titleEn]: <>(Creating a custom entity)
[metaDescriptionEn]: <>(Quite often, your plugin has to save data into a custom database table. Shopware 6's data abstraction layer fully supports custom entities, so you don't have to take care of the data handling at all.)
[hash]: <>(article:how_to_custom_entity)

## Overview

Quite often, your plugin has to save data into a custom database table.
Shopware 6's [data abstraction layer](./../2-internals/1-core/20-data-abstraction-layer/__categoryInfo.md) fully supports custom entities,
so you don't have to take care of the data handling at all.

## Plugin base class

So let's start with the plugin base class.

All it has to do, is to register your `services.xml` file by simply putting it into the proper directory `<plugin root>/src/Resources/config/`.
This way, Shopware 6 is able to automatically find and load your `services.xml` file.

*Note: You can change your plugin's `services.xml` location by overriding the method `getServicesFilePath` of your [plugin's base class](./../2-internals/4-plugins/020-plugin-base-class.md#getServicesFilePath).*

## The EntityDefinition class

The main entry point for custom entities is an `EntityDefinition` class.
For more information about what the `EntityDefinition` class does, have a look at the guide about the [data abstraction layer](./../2-internals/1-core/20-data-abstraction-layer/__categoryInfo.md).

Your custom entity, as well as your `EntityDefinition` and the `EntityCollection` classes, should be placed inside a folder
named after the domain it handles, e.g. "Checkout" if you were to include a Checkout entity.

In this example, they will be put into a directory called `src/Custom` inside of the plugin root directory.

```php
<?php declare(strict_types=1);

namespace Swag\CustomEntity\Custom;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class CustomEntityDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'custom_entity';
    
    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }
    
    public function getCollectionClass(): string
    {
        return CustomEntityCollection::class;
    }

    public function getEntityClass(): string
    {
        return CustomEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new StringField('technical_name', 'technicalName'),
        ]);
    }
}
```

As you can see, the `EntityDefinition` lists all available fields of your custom entity, as well as its name, its `EntityCollection` 
class and its actual entity class.
Keep in mind, that the return of your `getEntityName` method will be used for two cases:
- The database table name
- The repository name in the DI container (`<the-name>.repository`)

The methods `getCollectionClass` and `getEntityClass` are optional, **yet we highly recommend implementing them yourself
in your entity definition**.

The two missing classes, the `Entity` itself and the `EntityCollection`, will be created in the next steps.

## The entity class

The entity class itself is a simple value object, like a struct, which contains as much properties as fields in the definition, ignoring the ID field.

```php
<?php declare(strict_types=1);

namespace Swag\CustomEntity\Custom;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class CustomEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $technicalName;

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function setTechnicalName(string $technicalName): void
    {
        $this->technicalName = $technicalName;
    }
}
```

As you can see, it only holds the properties and its respective getters and setters, for the fields mentioned in the
`EntityDefinition` class.

## CustomEntityCollection

An `EntityCollection` class is a class, whose main purpose it is to hold one or more of your entities, when they are being read / searched.
It will be automatically returned by the DAL when dealing with the custom entity repository.

```php
<?php declare(strict_types=1);

namespace Swag\CustomEntity\Custom;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void              add(CustomEntity $entity)
 * @method void              set(string $key, CustomEntity $entity)
 * @method CustomEntity[]    getIterator()
 * @method CustomEntity[]    getElements()
 * @method CustomEntity|null get(string $key)
 * @method CustomEntity|null first()
 * @method CustomEntity|null last()
 */
class CustomEntityCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return CustomEntity::class;
    }
}
```

You should also add the annotation above the class to make sure your IDE knows how to properly handle your custom collection.
Make sure to replace every occurrence of `CustomEntity` in there with your actual entity class.

## Registering your custom entity

Now it's time to actually register your new entity in the DI container.
All you have to do is to register your `EntityDefinition` using the `shopware.entity.definition` tag.

This is how your `services.xml` could look like:
```xml
<?xml version="1.0" ?>

<container xmlns="http://symfony.com/schema/dic/services"
           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
           xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <service id="Swag\CustomEntity\Custom\CustomEntityDefinition">
            <tag name="shopware.entity.definition" entity="custom_entity" />
        </service>
    </services>
</container>
```

## Creating the table

Basically that's it for your custom entity.
Yet, there's a very important part missing: Creating the database table.
As already mentioned earlier, the database table **has to** be named after your chosen entity name.

You should create the database table using the plugin migration system.
For a short example how to use migrations, have a look [here](./170-plugin-migrations.md).
A more detailed explanation about the plugin migration system can be found in [this guide](./../2-internals/4-plugins/080-plugin-migrations.md).

In short:
Create a new directory named `src/Migration` in your plugin root and add a migration class like this in there:

```php
<?php declare(strict_types=1);

namespace Swag\CustomEntity\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1552484872Custom extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1552484872;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `custom_entity` (
    `id` BINARY(16) NOT NULL,
    `technical_name` VARCHAR(255) COLLATE utf8mb4_unicode_ci,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3),
    PRIMARY KEY (`id`)
)
    ENGINE = InnoDB
    DEFAULT CHARSET = utf8mb4
    COLLATE = utf8mb4_unicode_ci;
SQL;
        $connection->executeUpdate($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
```

## Dealing with your custom entity

Since the DAL automatically creates a repository for your custom entities, you can now ask the DAL to return some of your
custom data.

```php
/** @var EntityRepositoryInterface $customRepository */
$customRepository = $this->container->get('custom_entity.repository');
$customId = $customRepository->searchIds(
    (new Criteria())->addFilter(new EqualsFilter('technicalName', 'Foo')),
    Context::createDefaultContext()
)->getIds()[0];
```

In this example, the ID of your custom entity, whose technical name equals to 'FOO', is requested.

As a follow up, you might want to have a look at the documentation on [How to translate custom entities](./060-custom-entity-translations.md).

## Source

There's a GitHub repository available, containing this example source.
Check it out [here](https://github.com/shopware/swag-docs-custom-entity).
