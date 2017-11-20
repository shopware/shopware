<?php declare(strict_types=1);

namespace Shopware\Shop\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\LongTextField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\UuidField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Write\Flag\PrimaryKey;
use Shopware\Api\Write\Flag\Required;
use Shopware\Shop\Collection\ShopTemplateConfigPresetBasicCollection;
use Shopware\Shop\Collection\ShopTemplateConfigPresetDetailCollection;
use Shopware\Shop\Event\ShopTemplateConfigPreset\ShopTemplateConfigPresetWrittenEvent;
use Shopware\Shop\Repository\ShopTemplateConfigPresetRepository;
use Shopware\Shop\Struct\ShopTemplateConfigPresetBasicStruct;
use Shopware\Shop\Struct\ShopTemplateConfigPresetDetailStruct;

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
            (new UuidField('uuid', 'uuid'))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('shop_template_uuid', 'shopTemplateUuid', ShopTemplateDefinition::class))->setFlags(new Required()),
            (new StringField('name', 'name'))->setFlags(new Required()),
            (new LongTextField('description', 'description'))->setFlags(new Required()),
            (new LongTextField('element_values', 'elementValues'))->setFlags(new Required()),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new ManyToOneAssociationField('shopTemplate', 'shop_template_uuid', ShopTemplateDefinition::class, false),
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
