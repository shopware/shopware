<?php declare(strict_types=1);

namespace Shopware\Customer\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Write\Flag\PrimaryKey;
use Shopware\Api\Write\Flag\Required;
use Shopware\Customer\Collection\CustomerGroupTranslationBasicCollection;
use Shopware\Customer\Collection\CustomerGroupTranslationDetailCollection;
use Shopware\Customer\Event\CustomerGroupTranslation\CustomerGroupTranslationWrittenEvent;
use Shopware\Customer\Repository\CustomerGroupTranslationRepository;
use Shopware\Customer\Struct\CustomerGroupTranslationBasicStruct;
use Shopware\Customer\Struct\CustomerGroupTranslationDetailStruct;
use Shopware\Shop\Definition\ShopDefinition;

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
            (new FkField('customer_group_uuid', 'customerGroupUuid', CustomerGroupDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('language_uuid', 'languageUuid', ShopDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->setFlags(new Required()),
            new ManyToOneAssociationField('customerGroup', 'customer_group_uuid', CustomerGroupDefinition::class, false),
            new ManyToOneAssociationField('language', 'language_uuid', ShopDefinition::class, false),
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
