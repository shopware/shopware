<?php declare(strict_types=1);

namespace Shopware\Shop\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\LongTextField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\UuidField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Write\Flag\PrimaryKey;
use Shopware\Api\Write\Flag\Required;
use Shopware\Shop\Collection\ShopTemplateConfigFormFieldValueBasicCollection;
use Shopware\Shop\Collection\ShopTemplateConfigFormFieldValueDetailCollection;
use Shopware\Shop\Event\ShopTemplateConfigFormFieldValue\ShopTemplateConfigFormFieldValueWrittenEvent;
use Shopware\Shop\Repository\ShopTemplateConfigFormFieldValueRepository;
use Shopware\Shop\Struct\ShopTemplateConfigFormFieldValueBasicStruct;
use Shopware\Shop\Struct\ShopTemplateConfigFormFieldValueDetailStruct;

class ShopTemplateConfigFormFieldValueDefinition extends EntityDefinition
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
        return 'shop_template_config_form_field_value';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            (new UuidField('uuid', 'uuid'))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('shop_template_config_form_field_uuid', 'shopTemplateConfigFormFieldUuid', ShopTemplateConfigFormFieldDefinition::class))->setFlags(new Required()),
            (new FkField('shop_uuid', 'shopUuid', ShopDefinition::class))->setFlags(new Required()),
            (new LongTextField('value', 'value'))->setFlags(new Required()),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new ManyToOneAssociationField('shopTemplateConfigFormField', 'shop_template_config_form_field_uuid', ShopTemplateConfigFormFieldDefinition::class, false),
            new ManyToOneAssociationField('shop', 'shop_uuid', ShopDefinition::class, false),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return ShopTemplateConfigFormFieldValueRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return ShopTemplateConfigFormFieldValueBasicCollection::class;
    }

    public static function getWrittenEventClass(): string
    {
        return ShopTemplateConfigFormFieldValueWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return ShopTemplateConfigFormFieldValueBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }

    public static function getDetailStructClass(): string
    {
        return ShopTemplateConfigFormFieldValueDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return ShopTemplateConfigFormFieldValueDetailCollection::class;
    }
}
