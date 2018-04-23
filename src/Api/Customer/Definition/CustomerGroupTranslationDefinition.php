<?php declare(strict_types=1);

namespace Shopware\Api\Customer\Definition;

use Shopware\Api\Customer\Collection\CustomerGroupTranslationBasicCollection;
use Shopware\Api\Customer\Collection\CustomerGroupTranslationDetailCollection;
use Shopware\Api\Customer\Event\CustomerGroupTranslation\CustomerGroupTranslationDeletedEvent;
use Shopware\Api\Customer\Event\CustomerGroupTranslation\CustomerGroupTranslationWrittenEvent;
use Shopware\Api\Customer\Repository\CustomerGroupTranslationRepository;
use Shopware\Api\Customer\Struct\CustomerGroupTranslationBasicStruct;
use Shopware\Api\Customer\Struct\CustomerGroupTranslationDetailStruct;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\ReferenceVersionField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\VersionField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Language\Definition\LanguageDefinition;

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
