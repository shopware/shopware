[titleEn]: <>(Step 6: Entity translations)
[hash]: <>(article:bundle_translation)

While setting up the previous tables for the bundle, it felt like something is missing here.
After having a look at the `BundleEntity` once more, you might have figured out that it would be pretty nice to have a translatable name for the bundle as well.

The name could then be displayed in the Storefront later on, which comes in handy when you're providing multiple bundles for a single product.

First of all a very little bit of theory:
Since a name is a string, one would open the `BundleDefinition` now and just add another `StringField` with the name, well, `name`.
Also adjusting the `BundleEntity` would be necessary, so it knows the new field, same as the `swag_bundle` table migration, which now would need a new column.
While that would work, you would have to handle the translation saving and loading yourself, which sounds like a lot of boilerplate code.

Fortunately, Shopware 6 also has you covered on that subject.
Instead of creating a `StringField`, you should rather create a `TranslatedField`, which only requires you to provide a `propertyName`, you only define the name of the property.
The attentive might have noticed, that this means you didn't have to provide a `storageName` and therefore the `swag_bundle` table does **not** come with a `name` column.
Instead, translations in Shopware 6 are saved in a separate table, so we're dealing with an association here.
One more thing you probably learned earlier in this tutorial: When dealing with an association, you'll have to define both a field as well as an 'AssociationField' in your `BundleDefinition`.

For this, also add a new `TranslationsAssociationField`, a special association field from Shopware 6. This is also just a `OneToManyAssociationField`, but it also
lets Shopware 6 know, that it's dealing with a translation here. This is also necessary, so it can take care of loading your translation automatically later.

Here's your new `defineFields` method of your `BundleDefinition`:
```php
<?php declare(strict_types=1);

namespace Swag\BundleExample\Core\Content\Bundle;

 ...
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;

class BundleDefinition extends EntityDefinition
{
    ...
    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            new TranslatedField('name'),
            (new StringField('discount_type', 'discountType'))->addFlags(new Required()),
            (new FloatField('discount', 'discount'))->addFlags(new Required()),
            new TranslationsAssociationField(BundleTranslationDefinition::class, 'swag_bundle_id'),
            new ManyToManyAssociationField('products', ProductDefinition::class, BundleProductDefinition::class, 'bundle_id', 'product_id'),
        ]);
    }
}
```

Note both the new `TranslatedField` as well as the `TranslationsAssociationField` in between. While the first is self-explaining, the `TranslationsAssociationField` asks for two parameters.
First comes definition class, `BundleTranslationDefinition` in this case, which will be created in the next step, so don't worry about this. As always, the new definition will also come
with a new database table to contain the translated fields. This new table will have a column `swag_bundle_id` pointing at the ID of the respective bundle's ID.
And that's also the second parameter of this association field, the `referenceField` will be `swag_bundle_id`.

## Adding the migration

Extend your `Migration` class by the new translation table. Once again, only do this when you're
still developing your plugin, **never touch an existing Migration when your plugin is already being used!**

The translation table's columns should be the following:
<dl>
    <dt>swag_bundle_id</dt>
    <dd>
        Just as explained previously, this will refer to the bundle this translation belongs to. This is also a foreign key.
    </dd>
    
    <dt>language_id</dt>
    <dd>
        This will contain the ID of the language for this translation. This is also a foreign key.
    </dd>
    
    <dt>name</dt>
    <dd>
        The actual translated value, the translated name of the bundle.
    </dd>
    
    <dt>created_at</dt>
    <dd>
        Not much explanation required here. Just note, that there's also an `updated_at` column, because a translation can be updated and you
        might want to keep track about this.
    </dd>
</dl>

So here's your translation table's SQL, that you can add to your migration class:

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
            CREATE TABLE IF NOT EXISTS `swag_bundle_translation` (
              `swag_bundle_id` BINARY(16) NOT NULL,
              `language_id` BINARY(16) NOT NULL,
              `name` VARCHAR(255),
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`swag_bundle_id`, `language_id`),
              CONSTRAINT `fk.bundle_translation.bundle_id` FOREIGN KEY (`swag_bundle_id`)
                REFERENCES `swag_bundle` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.bundle_translation.language_id` FOREIGN KEY (`language_id`)
                REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }
}
```

## Setting up the translation definition

The translation is another aggregation to the `BundleEntity`, just like the `BundleProductDefinition`. Hence you're also supposed to place it in the `Aggregate` directory that you've
already used when adding the `BundleProductDefinition`.
Create a new directory called `BundleTranslation` here: `<plugin root>/src/Core/Content/Bundle/Aggregate/`
In there create a new class called `BundleTranslationDefinition`, which then extends from the `Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition`.
The `EntityTranslationDefinition` is taking care of some logic you'd have to implement yourself otherwise, e.g. adding and handling default fields, that every translation table needs,
such as a `language_id` column.

This time you have to override the following three methods in the `BundleTranslationDefinition`:
<dl>
    <dt>getEntityName</dt>
    <dd>
        You should know what to do here by now. Return the name of the translation table here, `swag_bundle_translation` sounds just fine.
    </dd>
    
    <dt>defineFields</dt>
    <dd>
        This method should also be familiar by now. Return a `FieldCollection` here, which contains all fields.
        A translation table comes with a lot of default columns, which you'd have do define yourself if you didn't extend from the `EntityTranslationDefinition`. Fortunately you don't have
        to define the ID field, the `CreatedAt` or `UpdatedAt` fields or the field, which saves the `language_id` of each translation.
        All you have to define here is your custom field, `name` in this case. Other than in the `BundleDefinition`, you're not just defining an association here, you're defining the actual
        field in the database table here, so you can and should use the `StringField` here now.
    </dd>
    
    <dt>getParentDefinitionClass</dt>
    <dd>
        This one is new, but one might be able to figure out what's asked for here. Return the FQCN to the entity here, whose translation this is.
        Thus return the FQCN to the `BundleDefinition` here.
    </dd>
</dl>

That's it for the required methods here, this would already work. Do you still remember why you added a custom `EntityCollection` and a custom `Entity` class to your `BundleDefinition`?
You've done that for the sake of auto-completion and thus improving the developer experience when working with your entity. This was skipped for the `BundleProductDefinition` though,
because you're never going to need a custom entity of a mapping table, you're never going to have to work with the mapping entity itself, rather than the mapped entity.
But do you think having a custom entity and a custom collection for translations makes sense here?
You're actually going to work with the translation entity itself and it being a generic entity would also leave your [IDE](https://en.wikipedia.org/wiki/Integrated_development_environment) not knowing properties this generic entity owns.
Guess what this is leading to, you'd want to have auto completion for translations as well.
But there's one more neat advantage of creating those custom classes: You can provide new helper methods, which could be helpful later on.
For example you could add a method `filterByLanguageId` to a custom collection class, so you could use this method to filter your entities in the custom `EntityCollection`
by a given language ID. Do not add it though, since you won't need it for this example plugin.

Thus also override the methods `getCollectionClass` and `getEntityClass` and return the FQCN to the not-yet existing entity and collection.

Here's what your `BundleTranslationDefinition` should look like then:
```php
<?php declare(strict_types=1);

namespace Swag\BundleExample\Core\Content\Bundle\Aggregate\BundleTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Swag\BundleExample\Core\Content\Bundle\BundleDefinition;

class BundleTranslationDefinition extends EntityTranslationDefinition
{
    public function getEntityName(): string
    {
        return 'swag_bundle_translation';
    }

    public function getCollectionClass(): string
    {
        return BundleTranslationCollection::class;
    }

    public function getEntityClass(): string
    {
        return BundleTranslationEntity::class;
    }

    public function getParentDefinitionClass(): string
    {
        return BundleDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name'))->addFlags(new Required()),
        ]);
    }
}
```

Just as explained previously, the methods `getEntityClass` and `getCollectionClass` are pointing to classes, that do not exist yet.
Those will be created in the next step.

## Creating the entity

Create a new class called `BundleTranslationEntity` in the same directory as the `BundleTranslationDefinition` and have it extend from
`Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity`. This just takes of handling the `language_id` property.

You'll have to add three properties here, one for the `bundle_id`, one for the actual name and one for the association to the main `BundleEntity`.
All of those properties need a getter and a setter again, so add those too.

Here's the example `BundleTranslationEntity`:
```php
<?php declare(strict_types=1);

namespace Swag\BundleExample\Core\Content\Bundle\Aggregate\BundleTranslation;

use Swag\BundleExample\Core\Content\Bundle\BundleEntity;
use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;

class BundleTranslationEntity extends TranslationEntity
{
    /**
     * @var string
     */
    protected $bundleId;

    /**
     * @var string|null
     */
    protected $name;

    /**
     * @var BundleEntity
     */
    protected $bundle;

    /**
     * @return string
     */
    public function getBundleId(): string
    {
        return $this->bundleId;
    }

    public function setBundleId(string $bundleId): void
    {
        $this->bundleId = $bundleId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getBundle(): BundleEntity
    {
        return $this->bundle;
    }

    public function setBundle(BundleEntity $bundle): void
    {
        $this->bundle = $bundle;
    }
}
```

Nothing too special about this custom entity.

## BundleTranslationCollection

Now create a class called `BundleTranslationCollection` in the same directory again. Since an `EntityCollection` does not have to handle
any field related stuff, there's no `TranslatedEntityCollection` or something alike, so just extend from the default `EntityCollection` here.

Just like in the `BundleCollection`, override the method `getExpectedClass` and return the FQCN for your `BundleTranslationEntity` here.

```php
<?php declare(strict_types=1);

namespace Swag\BundleExample\Core\Content\Bundle\Aggregate\BundleTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                         add(BundleTranslationEntity $entity)
 * @method void                         set(string $key, BundleTranslationEntity $entity)
 * @method BundleTranslationEntity[]    getIterator()
 * @method BundleTranslationEntity[]    getElements()
 * @method BundleTranslationEntity|null get(string $key)
 * @method BundleTranslationEntity|null first()
 * @method BundleTranslationEntity|null last()
 */
class BundleTranslationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return BundleTranslationEntity::class;
    }
}
```

## Registering the translation definition

Do not forget to register your custom definitions in the `services.xml` file, just like you've done for both the `BundleDefinition` as well as for the `BundleProductDefinition`.

```xml
<?xml version="1.0" ?>

<container xmlns="http://symfony.com/schema/dic/services"
           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
           xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">
    
    <services>
        ...

        <service id="Swag\BundleExample\Core\Content\Bundle\Aggregate\BundleTranslation\BundleTranslationDefinition">
            <tag name="shopware.entity.definition" entity="swag_bundle_translation" />
        </service>
    </services>
</container>
```

**Good news: You've got all entities and definitions required for this plugin set up now!**

Time to manage your bundles in the administration. Follow the [next step](./070-administration.md) to learn how that's done.
