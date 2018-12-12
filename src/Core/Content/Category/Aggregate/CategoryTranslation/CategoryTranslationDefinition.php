<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Aggregate\CategoryTranslation;

use Shopware\Core\Content\Catalog\CatalogDefinition;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CatalogField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\System\Language\LanguageDefinition;

class CategoryTranslationDefinition extends EntityTranslationDefinition
{
    public static function getEntityName(): string
    {
        return 'category_translation';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField('category_id', 'categoryId', CategoryDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new ReferenceVersionField(CategoryDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            new CatalogField(),

            (new FkField('language_id', 'languageId', LanguageDefinition::class))->setFlags(new PrimaryKey(), new Required()),

            (new StringField('name', 'name'))->setFlags(new Required()),
            new LongTextField('path_names', 'pathNames'),
            new LongTextField('meta_keywords', 'metaKeywords'),
            new StringField('meta_title', 'metaTitle'),
            new LongTextField('meta_description', 'metaDescription'),
            new StringField('cms_headline', 'cmsHeadline'),
            new LongTextField('cms_description', 'cmsDescription'),
            new CreatedAtField(),
            new UpdatedAtField(),
            new ManyToOneAssociationField('category', 'category_id', CategoryDefinition::class, false),
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, false),
            new ManyToOneAssociationField('catalog', 'catalog_id', CatalogDefinition::class, false, 'id'),
        ]);
    }

    public static function getCollectionClass(): string
    {
        return CategoryTranslationCollection::class;
    }

    public static function getEntityClass(): string
    {
        return CategoryTranslationEntity::class;
    }

    public static function getRootEntity(): ?string
    {
        return CategoryDefinition::class;
    }
}
