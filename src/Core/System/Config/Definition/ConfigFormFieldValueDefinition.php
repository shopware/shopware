<?php declare(strict_types=1);

namespace Shopware\System\Config\Definition;

use Shopware\System\Config\Collection\ConfigFormFieldValueBasicCollection;
use Shopware\System\Config\Collection\ConfigFormFieldValueDetailCollection;
use Shopware\System\Config\Event\ConfigFormFieldValue\ConfigFormFieldValueDeletedEvent;
use Shopware\System\Config\Event\ConfigFormFieldValue\ConfigFormFieldValueWrittenEvent;
use Shopware\System\Config\Repository\ConfigFormFieldValueRepository;
use Shopware\System\Config\Struct\ConfigFormFieldValueBasicStruct;
use Shopware\System\Config\Struct\ConfigFormFieldValueDetailStruct;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\IdField;
use Shopware\Api\Entity\Field\LongTextField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\ReferenceVersionField;
use Shopware\Api\Entity\Field\TenantIdField;
use Shopware\Api\Entity\Field\VersionField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Shop\Definition\ShopDefinition;

class ConfigFormFieldValueDefinition extends EntityDefinition
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
        return 'config_form_field_value';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            new TenantIdField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new VersionField(),

            (new FkField('config_form_field_id', 'configFormFieldId', ConfigFormFieldDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(ConfigFormFieldDefinition::class))->setFlags(new Required()),

            new FkField('shop_id', 'shopId', ShopDefinition::class),
            new ReferenceVersionField(ShopDefinition::class),

            (new LongTextField('value', 'value'))->setFlags(new Required()),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new ManyToOneAssociationField('configFormField', 'config_form_field_id', ConfigFormFieldDefinition::class, false),
            new ManyToOneAssociationField('shop', 'shop_id', ShopDefinition::class, false),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return ConfigFormFieldValueRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return ConfigFormFieldValueBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return ConfigFormFieldValueDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return ConfigFormFieldValueWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return ConfigFormFieldValueBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }

    public static function getDetailStructClass(): string
    {
        return ConfigFormFieldValueDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return ConfigFormFieldValueDetailCollection::class;
    }
}
