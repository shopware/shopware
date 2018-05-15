<?php declare(strict_types=1);

namespace Shopware\Content\Product\Definition;

use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\EntityExtensionInterface;
use Shopware\Framework\ORM\Field\FkField;
use Shopware\Framework\ORM\Field\FloatField;
use Shopware\Framework\ORM\Field\IdField;
use Shopware\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Framework\ORM\Field\StringField;
use Shopware\Framework\ORM\Field\TenantIdField;
use Shopware\Framework\ORM\Field\VersionField;
use Shopware\Framework\ORM\FieldCollection;
use Shopware\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Framework\ORM\Write\Flag\Required;
use Shopware\Application\Language\Definition\LanguageDefinition;
use Shopware\Content\Product\Collection\ProductSearchKeywordBasicCollection;
use Shopware\Content\Product\Collection\ProductSearchKeywordDetailCollection;
use Shopware\Content\Product\Event\ProductSearchKeyword\ProductSearchKeywordDeletedEvent;
use Shopware\Content\Product\Event\ProductSearchKeyword\ProductSearchKeywordWrittenEvent;
use Shopware\Content\Product\Repository\ProductSearchKeywordRepository;
use Shopware\Content\Product\Struct\ProductSearchKeywordBasicStruct;
use Shopware\Content\Product\Struct\ProductSearchKeywordDetailStruct;

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
