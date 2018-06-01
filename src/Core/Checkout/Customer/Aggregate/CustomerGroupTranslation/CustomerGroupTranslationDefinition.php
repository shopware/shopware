<?php declare(strict_types=1);

namespace Shopware\Checkout\Customer\Aggregate\CustomerGroupTranslation;

use Shopware\System\Language\LanguageDefinition;
use Shopware\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupDefinition;
use Shopware\Checkout\Customer\Aggregate\CustomerGroupTranslation\Collection\CustomerGroupTranslationBasicCollection;
use Shopware\Checkout\Customer\Aggregate\CustomerGroupTranslation\Collection\CustomerGroupTranslationDetailCollection;
use Shopware\Checkout\Customer\Aggregate\CustomerGroupTranslation\Event\CustomerGroupTranslationDeletedEvent;
use Shopware\Checkout\Customer\Aggregate\CustomerGroupTranslation\Event\CustomerGroupTranslationWrittenEvent;
use Shopware\Checkout\Customer\Aggregate\CustomerGroupTranslation\Struct\CustomerGroupTranslationBasicStruct;
use Shopware\Checkout\Customer\Aggregate\CustomerGroupTranslation\Struct\CustomerGroupTranslationDetailStruct;
use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\EntityExtensionInterface;
use Shopware\Framework\ORM\Field\FkField;
use Shopware\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Framework\ORM\Field\StringField;
use Shopware\Framework\ORM\FieldCollection;
use Shopware\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Framework\ORM\Write\Flag\Required;

class CustomerGroupTranslationDefinition extends EntityDefinition
{
    /**
     * @var FieldCollection
     */
    protected static $primaryKeys;

    /**
     * @var FieldCollection
     */
    protected static $fields;

    /**
     * @var EntityExtensionInterface[]
     */
    protected static $extensions = [];

    public static function getEntityName(): string
    {
        return 'customer_group_translation';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            (new FkField('customer_group_id', 'customerGroupId', CustomerGroupDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new ReferenceVersionField(CustomerGroupDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('language_id', 'languageId', LanguageDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->setFlags(new Required()),
            new ManyToOneAssociationField('customerGroup', 'customer_group_id', CustomerGroupDefinition::class, false),
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, false),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return CustomerGroupTranslationRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return CustomerGroupTranslationBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return CustomerGroupTranslationDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return CustomerGroupTranslationWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return CustomerGroupTranslationBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }

    public static function getDetailStructClass(): string
    {
        return CustomerGroupTranslationDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return CustomerGroupTranslationDetailCollection::class;
    }
}
