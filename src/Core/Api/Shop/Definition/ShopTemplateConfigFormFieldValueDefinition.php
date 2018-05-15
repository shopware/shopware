<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Definition;

use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\EntityExtensionInterface;
use Shopware\Framework\ORM\Field\DateField;
use Shopware\Framework\ORM\Field\FkField;
use Shopware\Framework\ORM\Field\IdField;
use Shopware\Framework\ORM\Field\LongTextField;
use Shopware\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Framework\ORM\Field\TenantIdField;
use Shopware\Framework\ORM\Field\VersionField;
use Shopware\Framework\ORM\FieldCollection;
use Shopware\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Framework\ORM\Write\Flag\Required;
use Shopware\Api\Shop\Collection\ShopTemplateConfigFormFieldValueBasicCollection;
use Shopware\Api\Shop\Collection\ShopTemplateConfigFormFieldValueDetailCollection;
use Shopware\Api\Shop\Event\ShopTemplateConfigFormFieldValue\ShopTemplateConfigFormFieldValueDeletedEvent;
use Shopware\Api\Shop\Event\ShopTemplateConfigFormFieldValue\ShopTemplateConfigFormFieldValueWrittenEvent;
use Shopware\Api\Shop\Repository\ShopTemplateConfigFormFieldValueRepository;
use Shopware\Api\Shop\Struct\ShopTemplateConfigFormFieldValueBasicStruct;
use Shopware\Api\Shop\Struct\ShopTemplateConfigFormFieldValueDetailStruct;

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
            new TenantIdField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new VersionField(),
            (new FkField('shop_template_config_form_field_id', 'shopTemplateConfigFormFieldId', ShopTemplateConfigFormFieldDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(ShopTemplateConfigFormFieldDefinition::class))->setFlags(new Required()),
            (new FkField('shop_id', 'shopId', ShopDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(ShopDefinition::class))->setFlags(new Required()),
            (new LongTextField('value', 'value'))->setFlags(new Required()),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new ManyToOneAssociationField('shopTemplateConfigFormField', 'shop_template_config_form_field_id', ShopTemplateConfigFormFieldDefinition::class, false),
            new ManyToOneAssociationField('shop', 'shop_id', ShopDefinition::class, false),
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

    public static function getDeletedEventClass(): string
    {
        return ShopTemplateConfigFormFieldValueDeletedEvent::class;
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
