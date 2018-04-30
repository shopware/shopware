<?php declare(strict_types=1);

namespace Shopware\Api\Product\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\FloatField;
use Shopware\Api\Entity\Field\IdField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\ReferenceVersionField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\TenantIdField;
use Shopware\Api\Entity\Field\VersionField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Language\Definition\LanguageDefinition;
use Shopware\Api\Product\Collection\ProductSearchKeywordBasicCollection;
use Shopware\Api\Product\Collection\ProductSearchKeywordDetailCollection;
use Shopware\Api\Product\Event\ProductSearchKeyword\ProductSearchKeywordDeletedEvent;
use Shopware\Api\Product\Event\ProductSearchKeyword\ProductSearchKeywordWrittenEvent;
use Shopware\Api\Product\Repository\ProductSearchKeywordRepository;
use Shopware\Api\Product\Struct\ProductSearchKeywordBasicStruct;
use Shopware\Api\Product\Struct\ProductSearchKeywordDetailStruct;

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
            new TenantIdField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new VersionField(),

            (new FkField('language_id', 'languageId', LanguageDefinition::class))->setFlags(new PrimaryKey(), new Required()),

            (new FkField('product_id', 'productId', ProductDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new ReferenceVersionField(ProductDefinition::class))->setFlags(new PrimaryKey(), new Required()),

            (new StringField('keyword', 'keyword'))->setFlags(new Required()),
            (new FloatField('ranking', 'ranking'))->setFlags(new Required()),
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, false),
            new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class, false),
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

    public static function getDeletedEventClass(): string
    {
        return ProductSearchKeywordDeletedEvent::class;
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
