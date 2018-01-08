<?php declare(strict_types=1);

namespace Shopware\Api\Product\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Product\Collection\ProductCategoryTreeBasicCollection;
use Shopware\Api\Product\Event\ProductCategoryTree\ProductCategoryTreeWrittenEvent;
use Shopware\Api\Product\Repository\ProductCategoryTreeRepository;
use Shopware\Api\Product\Struct\ProductCategoryTreeBasicStruct;

class ProductCategoryTreeDefinition extends EntityDefinition
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
        return 'product_category_tree';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            (new StringField('category_id', 'categoryId'))->setFlags(new Required()),
            (new StringField('product_id', 'productId'))->setFlags(new Required()),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return ProductCategoryTreeRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return ProductCategoryTreeBasicCollection::class;
    }

    public static function getWrittenEventClass(): string
    {
        return ProductCategoryTreeWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return ProductCategoryTreeBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }
}
