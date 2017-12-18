<?php declare(strict_types=1);

namespace Shopware\Api\Product\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\FloatField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\UuidField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Product\Collection\ProductSearchKeywordBasicCollection;
use Shopware\Api\Product\Collection\ProductSearchKeywordDetailCollection;
use Shopware\Api\Product\Event\ProductSearchKeyword\ProductSearchKeywordWrittenEvent;
use Shopware\Api\Product\Repository\ProductSearchKeywordRepository;
use Shopware\Api\Product\Struct\ProductSearchKeywordBasicStruct;
use Shopware\Api\Product\Struct\ProductSearchKeywordDetailStruct;
use Shopware\Api\Shop\Definition\ShopDefinition;

class ProductSearchKeywordDefinition extends EntityDefinition
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
        return 'product_search_keyword';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            (new UuidField('uuid', 'uuid'))->setFlags(new PrimaryKey(), new Required()),
            (new StringField('keyword', 'keyword'))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('shop_uuid', 'shopUuid', ShopDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('product_uuid', 'productUuid', ProductDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new FloatField('ranking', 'ranking'))->setFlags(new Required()),
            new ManyToOneAssociationField('shop', 'shop_uuid', ShopDefinition::class, false),
            new ManyToOneAssociationField('product', 'product_uuid', ProductDefinition::class, false),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return ProductSearchKeywordRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return ProductSearchKeywordBasicCollection::class;
    }

    public static function getWrittenEventClass(): string
    {
        return ProductSearchKeywordWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return ProductSearchKeywordBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }

    public static function getDetailStructClass(): string
    {
        return ProductSearchKeywordDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return ProductSearchKeywordDetailCollection::class;
    }
}
