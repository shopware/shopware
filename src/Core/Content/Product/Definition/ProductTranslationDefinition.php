<?php declare(strict_types=1);

namespace Shopware\Content\Product\Definition;

use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\EntityExtensionInterface;
use Shopware\Framework\ORM\Field\CatalogField;
use Shopware\Framework\ORM\Field\FkField;
use Shopware\Framework\ORM\Field\LongTextField;
use Shopware\Framework\ORM\Field\LongTextWithHtmlField;
use Shopware\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Framework\ORM\Field\StringField;
use Shopware\Framework\ORM\Field\VersionField;
use Shopware\Framework\ORM\FieldCollection;
use Shopware\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Framework\ORM\Write\Flag\Required;
use Shopware\Application\Language\Definition\LanguageDefinition;
use Shopware\Content\Product\Collection\ProductTranslationBasicCollection;
use Shopware\Content\Product\Collection\ProductTranslationDetailCollection;
use Shopware\Content\Product\Event\ProductTranslation\ProductTranslationDeletedEvent;
use Shopware\Content\Product\Event\ProductTranslation\ProductTranslationWrittenEvent;
use Shopware\Content\Product\Repository\ProductTranslationRepository;
use Shopware\Content\Product\Struct\ProductTranslationBasicStruct;
use Shopware\Content\Product\Struct\ProductTranslationDetailStruct;

class ProductTranslationDefinition extends EntityDefinition
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
        return 'product_translation';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            (new FkField('product_id', 'productId', ProductDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new ReferenceVersionField(ProductDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            new CatalogField(),

            (new FkField('language_id', 'languageId', LanguageDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            new StringField('additional_text', 'additionalText'),
            new StringField('name', 'name'),
            new LongTextField('keywords', 'keywords'),
            new LongTextField('description', 'description'),
            new LongTextWithHtmlField('description_long', 'descriptionLong'),
            new StringField('meta_title', 'metaTitle'),
            new StringField('pack_unit', 'packUnit'),
            new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class, false),
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, false),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return ProductTranslationRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return ProductTranslationBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return ProductTranslationDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return ProductTranslationWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return ProductTranslationBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }

    public static function getDetailStructClass(): string
    {
        return ProductTranslationDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return ProductTranslationDetailCollection::class;
    }
}
