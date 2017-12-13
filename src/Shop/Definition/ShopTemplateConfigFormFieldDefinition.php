<?php declare(strict_types=1);

namespace Shopware\Shop\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\BoolField;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\IntField;
use Shopware\Api\Entity\Field\LongTextField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\OneToManyAssociationField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\UuidField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Shop\Collection\ShopTemplateConfigFormFieldBasicCollection;
use Shopware\Shop\Collection\ShopTemplateConfigFormFieldDetailCollection;
use Shopware\Shop\Event\ShopTemplateConfigFormField\ShopTemplateConfigFormFieldWrittenEvent;
use Shopware\Shop\Repository\ShopTemplateConfigFormFieldRepository;
use Shopware\Shop\Struct\ShopTemplateConfigFormFieldBasicStruct;
use Shopware\Shop\Struct\ShopTemplateConfigFormFieldDetailStruct;

class ShopTemplateConfigFormFieldDefinition extends EntityDefinition
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
        return 'shop_template_config_form_field';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            (new UuidField('uuid', 'uuid'))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('shop_template_uuid', 'shopTemplateUuid', ShopTemplateDefinition::class))->setFlags(new Required()),
            (new FkField('shop_template_config_form_uuid', 'shopTemplateConfigFormUuid', ShopTemplateConfigFormDefinition::class))->setFlags(new Required()),
            (new StringField('type', 'type'))->setFlags(new Required()),
            (new StringField('name', 'name'))->setFlags(new Required()),
            new IntField('position', 'position'),
            new LongTextField('default_value', 'defaultValue'),
            new LongTextField('selection', 'selection'),
            new StringField('field_label', 'fieldLabel'),
            new StringField('support_text', 'supportText'),
            new BoolField('allow_blank', 'allowBlank'),
            new LongTextField('attributes', 'attributes'),
            new BoolField('less_compatible', 'lessCompatible'),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new ManyToOneAssociationField('shopTemplate', 'shop_template_uuid', ShopTemplateDefinition::class, false),
            new ManyToOneAssociationField('shopTemplateConfigForm', 'shop_template_config_form_uuid', ShopTemplateConfigFormDefinition::class, false),
            new OneToManyAssociationField('values', ShopTemplateConfigFormFieldValueDefinition::class, 'shop_template_config_form_field_uuid', false, 'uuid'),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return ShopTemplateConfigFormFieldRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return ShopTemplateConfigFormFieldBasicCollection::class;
    }

    public static function getWrittenEventClass(): string
    {
        return ShopTemplateConfigFormFieldWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return ShopTemplateConfigFormFieldBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }

    public static function getDetailStructClass(): string
    {
        return ShopTemplateConfigFormFieldDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return ShopTemplateConfigFormFieldDetailCollection::class;
    }
}
