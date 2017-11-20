<?php declare(strict_types=1);

namespace Shopware\Shop\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\LongTextField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\OneToManyAssociationField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\UuidField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Write\Flag\PrimaryKey;
use Shopware\Api\Write\Flag\Required;
use Shopware\Shop\Collection\ShopTemplateConfigFormBasicCollection;
use Shopware\Shop\Collection\ShopTemplateConfigFormDetailCollection;
use Shopware\Shop\Event\ShopTemplateConfigForm\ShopTemplateConfigFormWrittenEvent;
use Shopware\Shop\Repository\ShopTemplateConfigFormRepository;
use Shopware\Shop\Struct\ShopTemplateConfigFormBasicStruct;
use Shopware\Shop\Struct\ShopTemplateConfigFormDetailStruct;

class ShopTemplateConfigFormDefinition extends EntityDefinition
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
        return 'shop_template_config_form';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            (new UuidField('uuid', 'uuid'))->setFlags(new PrimaryKey(), new Required()),
            new FkField('parent_uuid', 'parentUuid', self::class),
            (new FkField('shop_template_uuid', 'shopTemplateUuid', ShopTemplateDefinition::class))->setFlags(new Required()),
            (new StringField('type', 'type'))->setFlags(new Required()),
            (new StringField('name', 'name'))->setFlags(new Required()),
            new StringField('title', 'title'),
            new LongTextField('options', 'options'),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new ManyToOneAssociationField('parent', 'parent_uuid', self::class, false),
            new ManyToOneAssociationField('shopTemplate', 'shop_template_uuid', ShopTemplateDefinition::class, false),
            new OneToManyAssociationField('fields', ShopTemplateConfigFormFieldDefinition::class, 'shop_template_config_form_uuid', false, 'uuid'),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return ShopTemplateConfigFormRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return ShopTemplateConfigFormBasicCollection::class;
    }

    public static function getWrittenEventClass(): string
    {
        return ShopTemplateConfigFormWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return ShopTemplateConfigFormBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }

    public static function getDetailStructClass(): string
    {
        return ShopTemplateConfigFormDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return ShopTemplateConfigFormDetailCollection::class;
    }
}
