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
use Shopware\Framework\ORM\Field\StringField;
use Shopware\Framework\ORM\Field\TenantIdField;
use Shopware\Framework\ORM\Field\VersionField;
use Shopware\Framework\ORM\FieldCollection;
use Shopware\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Framework\ORM\Write\Flag\Required;
use Shopware\Api\Shop\Collection\ShopTemplateConfigPresetBasicCollection;
use Shopware\Api\Shop\Collection\ShopTemplateConfigPresetDetailCollection;
use Shopware\Api\Shop\Event\ShopTemplateConfigPreset\ShopTemplateConfigPresetDeletedEvent;
use Shopware\Api\Shop\Event\ShopTemplateConfigPreset\ShopTemplateConfigPresetWrittenEvent;
use Shopware\Api\Shop\Repository\ShopTemplateConfigPresetRepository;
use Shopware\Api\Shop\Struct\ShopTemplateConfigPresetBasicStruct;
use Shopware\Api\Shop\Struct\ShopTemplateConfigPresetDetailStruct;

class ShopTemplateConfigPresetDefinition extends EntityDefinition
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
        return 'shop_template_config_preset';
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
            (new FkField('shop_template_id', 'shopTemplateId', ShopTemplateDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(ShopTemplateDefinition::class))->setFlags(new Required()),
            (new StringField('name', 'name'))->setFlags(new Required()),
            (new LongTextField('description', 'description'))->setFlags(new Required()),
            (new LongTextField('element_values', 'elementValues'))->setFlags(new Required()),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new ManyToOneAssociationField('shopTemplate', 'shop_template_id', ShopTemplateDefinition::class, false),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return ShopTemplateConfigPresetRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return ShopTemplateConfigPresetBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return ShopTemplateConfigPresetDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return ShopTemplateConfigPresetWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return ShopTemplateConfigPresetBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }

    public static function getDetailStructClass(): string
    {
        return ShopTemplateConfigPresetDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return ShopTemplateConfigPresetDetailCollection::class;
    }
}
