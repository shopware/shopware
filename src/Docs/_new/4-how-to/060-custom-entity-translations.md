[titleEn]: <>(Translating a custom entity)
[metaDescriptionEn]: <>(This HowTo will handle how to properly translate your plugin's custom entities.)

## Overview

This HowTo will handle how to properly translate your custom entities.
Since [this HowTo](./050-custom-entity.md) already covered creating a custom entity and introducing it to the
Shopware platform, this won't be explained here.
Furthermore, this example will build upon the fact, that you already got a plugin containing a custom entity.

## Translatable fields in definition

Imagine you're dealing with with an `EntityDefinition` class like this one:

```php
<?php declare(strict_types=1);

namespace Swag\CustomEntityTranslations\Custom;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class CustomEntityDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'custom_entity';
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new StringField('technical_name', 'technicalName'),
            new CreatedAtField(),
            new UpdatedAtField(),
        ]);
    }

    public static function getCollectionClass(): string
    {
        return CustomEntityCollection::class;
    }

    public static function getEntityClass(): string
    {
        return CustomEntity::class;
    }
}
```

This class defined a string field `technical_name`, which must not be translated, since its main purpose is to be a unique identifier, next to the ID.
Now imagine this class would also provide a `label` field, which is also basically a `StringField`.
Simply adding a `StringField` here is tempting, isn't it?
Since a `label` should be translatable, you have to add a `TranslatedField` instead.

```php
protected static function defineFields(): FieldCollection
{
    return new FieldCollection([
        (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
        new StringField('technical_name', 'technicalName'),
        new TranslatedField('label'),
        new CreatedAtField(),
        new UpdatedAtField(),
        (new TranslationsAssociationField(CustomEntityTranslationDefinition::class, 'custom_entity_id'))->addFlags(new Required()),
    ]);
}
```

Additionally to the `TranslatedField`, you also need an `TranslationsAssociationField` to setup the actual association.

## Translation association in entity

Your custom entity now comes with a new field named 'label', marked as a `TranslatedField`.
Next thing you gotta add this translation association to your custom entity.

```php
<?php declare(strict_types=1);

namespace Swag\CustomEntityTranslations\Custom;

use Swag\CustomEntityTranslations\Custom\Aggregate\CustomTranslation\CustomEntityTranslationCollection;
...

class CustomEntity extends Entity
{
    ...

    /**
     * @var CustomEntityTranslationCollection|null
     */
    protected $translations;

    ...

    public function getTranslations(): ?CustomEntityTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(CustomEntityTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }
}

```

The class `CustomEntityTranslationCollection` will be added in the next steps, don't worry.

## Custom translation entity

Since the translation is going to be saved in another table just for your custom entity,
the translations need an own `Entity`, `EntityDefinition` and an `EntityCollection` class, such as the `CustomEntityTranslationCollection` mentioned above.

So, once more, let's create all these classes. By default, the Shopware platform places the entity translation classes
inside a directory called `Aggregate`.
In this example, the directory structure would look like this:

```
<plugin-root>
    └──src
        ├── Custom
        │    ├── Aggregate
        │    │    └── CustomEntityTranslation
        │    │        └── CustomTranslationEntity.php
        │    │        ...
        │    ├── CustomEntity.php
        │    └── CustomEntityCollection.php
        │    ...
        └──  <BaseClass>.php
```

### Translation Entity Definition

Since creating an `EntityCollection` was already explained in the [previous HowTo](./050-custom-entity.md), only the differences are going to be explained here.

```php
<?php declare(strict_types = 1);

namespace Swag\CustomEntityTranslations\Custom\Aggregate\CustomTranslation;

use Swag\CustomEntityTranslations\Custom\CustomEntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class CustomEntityTranslationDefinition extends EntityTranslationDefinition
{
    public static function getEntityName(): string
    {
        return 'custom_entity_translation';
    }

    public static function getCollectionClass(): string
    {
        return CustomEntityTranslationCollection::class;
    }

    public static function getEntityClass(): string
    {
        return CustomTranslationEntity::class;
    }

    public static function getParentDefinitionClass(): string
    {
        return CustomEntityDefinition::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('label', 'label')),
        ]);
    }
}
```

First of all you need to extend from `EntityTranslationDefinition` here.
Also have a look at the "new" method `getParentDefinitionClass`, which only has to to the parent definition class,
`CustomEntityDefinition` in this case.

The fields only have to contain the actually translatable fields from the parent definition, `label` in this case.
Everything else is already handled by the `EntityTranslationDefinition`, for example adding an `updatedAt`, a `createdAt`
and a `languageId` field.

### Translation Entity

The entity class for the translation also comes with some new requirements.
First of all, the entity has to extend from `TranslationEntity`.
This way the translation default fields mentioned in the previous step, like `languageId`, are already added
as a property with getters and setters. 
Additional to that you to add your custom translated field(s), `label` in this example, as well as the
parent's id, `customEntityId` in this case, as well as a property for the actual `CustomEntity` object.

```php
<?php declare(strict_types = 1);

namespace Swag\CustomEntityTranslations\Custom\Aggregate\CustomTranslation;

use Swag\CustomEntityTranslations\Custom\CustomEntity;
use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;

class CustomTranslationEntity extends TranslationEntity
{
    /**
     * @var string
     */
    protected $customEntityId;

    /**
     * @var string|null
     */
    protected $label;

    /**
     * @var CustomEntity|null
     */
    protected $custom;

    /**
     * @return string
     */
    public function getCustomEntityId(): string
    {
        return $this->customEntityId;
    }

    /**
     * @param string $customEntityId
     */
    public function setCustomEntityId(string $customEntityId): void
    {
        $this->customEntityId = $customEntityId;
    }

    /**
     * @return string
     */
    public function getLabel(): ?string
    {
        return $this->label;
    }

    /**
     * @param string $label
     */
    public function setLabel(?string $label): void
    {
        $this->label = $label;
    }

    /**
     * @return CustomEntity|null
     */
    public function getCustom(): ?CustomEntity
    {
        return $this->custom;
    }

    /**
     * @param CustomEntity|null $custom
     */
    public function setCustom(CustomEntity $custom): void
    {
        $this->custom = $custom;
    }
}
```

### Translation entity collection

Your custom translation entity also has to come with a respective collection class, which also extends from `EntityCollection`.

```php
<?php declare(strict_types = 1);

namespace Swag\CustomEntityTranslations\Custom\Aggregate\CustomTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                         add(CustomTranslationEntity $entity)
 * @method void                         set(string $key, CustomTranslationEntity $entity)
 * @method CustomTranslationEntity[]    getIterator()
 * @method CustomTranslationEntity[]    getElements()
 * @method CustomTranslationEntity|null get(string $key)
 * @method CustomTranslationEntity|null first()
 * @method CustomTranslationEntity|null last()
 */
class CustomEntityTranslationCollection extends EntityCollection
{
    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (CustomTranslationEntity $customTranslationEntity) use ($id) {
            return $customTranslationEntity->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return CustomTranslationEntity::class;
    }
}
```

Note the helper method `filterByLanguageId`, which is **not required**.
It comes in handy, when searching for the translation for a given language.

## Migration for the translation table

Finally, you have to adjust your plugin migration to also create your translation table.

```php
<?php declare(strict_types=1);

namespace Swag\CustomEntityTranslations\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1552548655Custom extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1552548655;
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

CREATE TABLE IF NOT EXISTS `custom_entity_translation` (
    `custom_entity_id` BINARY(16) NOT NULL,
    `language_id` BINARY(16) NOT NULL,
    `label` VARCHAR(255) NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3),
      PRIMARY KEY (`custom_entity_id`, `language_id`),
      CONSTRAINT `fk.custom_entity_translation.language_id` FOREIGN KEY (`language_id`)
        REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
      CONSTRAINT `fk.custom_entity_translation.custom_entity_id` FOREIGN KEY (`custom_entity_id`)
        REFERENCES `custom_entity` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
    )
    ENGINE = InnoDB
    DEFAULT CHARSET = utf8mb4
    COLLATE = utf8mb4_unicode_ci;
SQL;
        $connection->executeQuery($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
```

The columns `language_id`, `created_at` and `updated_at` are part of your entity by default, so you have to add them in
your table.
Also, the column `custom_entity_id` comes by default, but is obviously named after your parent entity, `custom_entity` in this case.

Note the primary key definition and the foreign keys, as they are also very important for your database.
You might have noticed, that the translation table does not come with an actual ID column - the primary key consists
of the parent entity ID and the language ID.

## Reading your custom entity translations

Reading translations for your entity works exactly like reading an association for your entity,
since it technically is an association.

```php
/** @var EntityRepositoryInterface $customRepository */
$customRepository = $this->container->get('custom_entity.repository');
/** @var CustomEntity $customEntity */
$customEntity = $customRepository->search(
    (new Criteria())->addFilter(new EqualsFilter('technicalName', 'Foo'))->addAssociation('custom_entity.translations'),
    Context::createDefaultContext()
)->first();
$customEntityTranslation = $customEntity->getTranslations()->filterByLanguageId(Defaults::LANGUAGE_SYSTEM)->first();
```

In this example, the ID of your custom entity, whose technical name equals to 'FOO', is requested.
Additional to that, the translation for the entity is read.

## Source

There's a GitHub repository available, containing this example source.
Check it out [here](https://github.com/shopware/swag-docs-custom-entity-translations).