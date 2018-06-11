<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductTranslation;

use Shopware\Core\Content\Catalog\ORM\CatalogField;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\Collection\ProductTranslationBasicCollection;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\Collection\ProductTranslationDetailCollection;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\Event\ProductTranslationDeletedEvent;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\Event\ProductTranslationWrittenEvent;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\Struct\ProductTranslationBasicStruct;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\Struct\ProductTranslationDetailStruct;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\EntityExtensionInterface;
use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\LongTextField;
use Shopware\Core\Framework\ORM\Field\LongTextWithHtmlField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Core\Framework\ORM\Field\StringField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\System\Language\LanguageDefinition;

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

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
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
