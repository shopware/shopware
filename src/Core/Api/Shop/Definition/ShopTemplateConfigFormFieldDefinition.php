<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Definition;

use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\EntityExtensionInterface;
use Shopware\Framework\ORM\Field\BoolField;
use Shopware\Framework\ORM\Field\DateField;
use Shopware\Framework\ORM\Field\FkField;
use Shopware\Framework\ORM\Field\IdField;
use Shopware\Framework\ORM\Field\IntField;
use Shopware\Framework\ORM\Field\LongTextField;
use Shopware\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Framework\ORM\Field\StringField;
use Shopware\Framework\ORM\Field\TenantIdField;
use Shopware\Framework\ORM\Field\VersionField;
use Shopware\Framework\ORM\FieldCollection;
use Shopware\Framework\ORM\Write\Flag\CascadeDelete;
use Shopware\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Framework\ORM\Write\Flag\Required;
use Shopware\Framework\ORM\Write\Flag\SearchRanking;
use Shopware\Api\Shop\Collection\ShopTemplateConfigFormFieldBasicCollection;
use Shopware\Api\Shop\Collection\ShopTemplateConfigFormFieldDetailCollection;
use Shopware\Api\Shop\Event\ShopTemplateConfigFormField\ShopTemplateConfigFormFieldDeletedEvent;
use Shopware\Api\Shop\Event\ShopTemplateConfigFormField\ShopTemplateConfigFormFieldWrittenEvent;
use Shopware\Api\Shop\Repository\ShopTemplateConfigFormFieldRepository;
use Shopware\Api\Shop\Struct\ShopTemplateConfigFormFieldBasicStruct;
use Shopware\Api\Shop\Struct\ShopTemplateConfigFormFieldDetailStruct;

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
            new TenantIdField(),
            new VersionField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('shop_template_id', 'shopTemplateId', ShopTemplateDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(ShopTemplateDefinition::class))->setFlags(new Required()),
            (new FkField('shop_template_config_form_id', 'shopTemplateConfigFormId', ShopTemplateConfigFormDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(ShopTemplateConfigFormDefinition::class))->setFlags(new Required()),
            (new StringField('type', 'type'))->setFlags(new Required()),
            (new StringField('name', 'name'))->setFlags(new Required(), new SearchRanking(self::HIGH_SEARCH_RANKING)),
            new IntField('position', 'position'),
            new LongTextField('default_value', 'defaultValue'),
            new LongTextField('selection', 'selection'),
            (new StringField('field_label', 'fieldLabel'))->setFlags(new SearchRanking(self::HIGH_SEARCH_RANKING)),
            new StringField('support_text', 'supportText'),
            new BoolField('allow_blank', 'allowBlank'),
            new LongTextField('attributes', 'attributes'),
            new BoolField('less_compatible', 'lessCompatible'),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new ManyToOneAssociationField('shopTemplate', 'shop_template_id', ShopTemplateDefinition::class, false),
            new ManyToOneAssociationField('shopTemplateConfigForm', 'shop_template_config_form_id', ShopTemplateConfigFormDefinition::class, false),
            (new OneToManyAssociationField('values', ShopTemplateConfigFormFieldValueDefinition::class, 'shop_template_config_form_field_id', false, 'id'))->setFlags(new CascadeDelete()),
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

    public static function getDeletedEventClass(): string
    {
        return ShopTemplateConfigFormFieldDeletedEvent::class;
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
