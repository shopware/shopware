[titleEn]: <>(Content translations)
[hash]: <>(article:developer_translations)

# Content translations

## DAL
This guide will handle how to properly translate your custom entities.

### Translatable fields in definition

Imagine you're dealing with an `EntityDefinition` class like this one:

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

This class defined a string field `technical_name`, which must not be translated, since its main purpose is to be a unique identifier, next to the ID.
Now imagine this class would also provide a `label` field, which is also basically a `StringField`.
Simply adding a `StringField` here is tempting, isn't it?
Since a `label` should be translatable, you have to add a `TranslatedField` instead.

```php
protected function defineFields(): FieldCollection
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

### Translation association in entity

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

### Custom translation entity

Since the translation is going to be saved in another table just for your custom entity,
the translations need an own `Entity`, `EntityDefinition` and an `EntityCollection` class, such as the `CustomEntityTranslationCollection` mentioned above.

So, once more, let's create all these classes. By default, Shopware 6 places the entity translation classes
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

#### Translation Entity Definition

First of all you need to extend from `EntityTranslationDefinition` here.
Also have a look at the "new" method `getParentDefinitionClass`, which only has to to the parent definition class,
`CustomEntityDefinition` in this case.

The fields only have to contain the actually translatable fields from the parent definition, `label` in this case.
Everything else is already handled by the `EntityTranslationDefinition`, for example adding an `updatedAt`, a `createdAt`
and a `languageId` field.

```php
<?php declare(strict_types=1);

namespace Swag\CustomEntityTranslations\Custom\Aggregate\CustomTranslation;

use Swag\CustomEntityTranslations\Custom\CustomEntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class CustomEntityTranslationDefinition extends EntityTranslationDefinition
{
    public function getEntityName(): string
    {
        return 'custom_entity_translation';
    }

    public function getCollectionClass(): string
    {
        return CustomEntityTranslationCollection::class;
    }

    public function getEntityClass(): string
    {
        return CustomTranslationEntity::class;
    }

    protected function getParentDefinitionClass(): string
    {
        return CustomEntityDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('label', 'label')),
        ]);
    }
}
```

#### Translation Entity

The entity class for the translation also comes with some new requirements.
First of all, the entity has to extend from `TranslationEntity`.
This way the translation default fields mentioned in the previous step, like `languageId`, are already added
as a property with getters and setters. 
Additional to that you to add your custom translated field(s), `label` in this example, as well as the
parent's id, `customEntityId` in this case, as well as a property for the actual `CustomEntity` object.

```php
<?php declare(strict_types=1);

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

#### Translation entity collection

Your custom translation entity also has to come with a respective collection class, which also extends from `EntityCollection`.

```php
<?php declare(strict_types=1);

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

### Registering your custom entity

Now it's time to actually register your new entity in the DI container.
All you have to do is to register your `EntityDefinition` using the `shopware.entity.definition` tag.

This is how your `services.xml` could look like:
```xml
<?xml version="1.0" ?>

<container xmlns="http://symfony.com/schema/dic/services"
           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
           xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <service id="Swag\CustomEntityTranslations\Custom\CustomEntityDefinition">
            <tag name="shopware.entity.definition" entity="custom_entity" />
        </service>

        <service id="Swag\CustomEntityTranslations\Custom\Aggregate\CustomTranslation\CustomEntityTranslationDefinition">
            <tag name="shopware.entity.definition" entity="custom_entity_translation" />
        </service>
    </services>
</container>
```

### Migration for the translation table

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
        $connection->executeUpdate($sql);
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

### Reading your custom entity translations

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

## Administration

### Global usage

The content language in the administration is globally. When you change you content language then all modules work
with this content language. You can find the active content language id in the context object.

```javascript
Shopware.Context.api.languageId // active content language
Shopware.Context.api.systemLanguageId // default content language
```

The repository uses the active language id for fetching data. It saves them automatically to the right property. Here an 
example with English as the default language:

Active language is English:
```javascript
const demoManufacturer = {
    id: '143daac4cc354634a4955ca6503fcde3',
    name: 'Shopware',
    description: 'This is the manufacturer of the best e-commerce solution in the world.',
    translated: {
        name: 'Shopware',
        description: 'This is the manufacturer of the best e-commerce solution in the world.'
    }
}
```

Active language is German:
```javascript
const demoManufacturer = {
    id: '143daac4cc354634a4955ca6503fcde3',
    name: null,
    description: 'Der Hersteller der weltweit besten E-Commerce Lösung.',
    translated: {
        name: 'Shopware',
        description: 'Der Hersteller der weltweit besten E-Commerce Lösung.'
    }
}
```

### How to modify translatable values

You should work on the main property (e.g. `demoManufacturer.name`) when you want to change the actual data. Shopware has
the functionality to inherit from the default language. Therefore it could be possible that some properties are null. In our
example the name in German matches the English name. Then we don´t need to set the name twice because the name is inherited
from the English language.

To get the actual representative value you can use the `translated` property. This property is read-only and should not be 
modified. It is helpful for listings or when you want to use the default value as an placeholder text in input fields.

### Best practice for changing the content language

You should only use the `sw-language-switch` component when you want to change the content language. The reason for this is
that it combines the best practices to change your language:

```vue
<sw-language-switch
    :abortChangeFunction="abortOnLanguageChange"
    :saveChangesFunction="saveOnLanguageChange"
    @on-change="onChangeLanguage">
</sw-language-switch>
```

The two function properties `abortChangeFunction` and `saveOnLanguageChange` should be used to provide the best experience
for changing the language.

To validate if the content language should be allowed to switch you can use the `abortChangeFunction`. 
There are reason why this could be dangerous. One reason is when the user changed data but does not save them yet.
When the provided function return `true`, then there will be a warning modal which notify the user that there are unsaved
data.

```javascript
abortOnLanguageChange() {
    return this.manufacturerRepository.hasChanges(this.manufacturer);
}
```

In this warning the user can directly save the data if he wants. Then it will call the `saveChangesFunction` and wait
until the Promise is resolved. After everything is saved the content language get switched.

```javascript
saveOnLanguageChange() {
    return this.onSave();
}
```

Now the content language was successfully switched. Our actual data is not updated yet. This is why we implemented our
`onChangeLanguage` function for the `on-change` event. It reloads automatically all data so that the user see now the data
in the new selected content language.

```javascript
onChangeLanguage() {
    this.loadEntityData();
}
```

## Storefront

The translated content in the storefront is similar to the administration. You have
the actual representative value in `entity.translated.yourProperty`.

You can access this values in two ways. The first way is to use a twig filter. This filter
is get called with `trans`. This should be the best solution in most ways.

```twig
{{ manufacturer.name|trans }}
```

Another possibility is to get the property directly from the `translated` value.

````twig
{{ manufacturer.translated.name }}
````

For security reason it is useful to sanitize the content values before you output them.
You can use the `sw_sanitize` filter to do this.
````twig
{{ manufacturer.name|trans|sw_sanitize }}
{{ manufacturer.translated.name|sw_sanitize }}
````
