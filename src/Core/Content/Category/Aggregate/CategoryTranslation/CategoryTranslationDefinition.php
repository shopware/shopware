<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Aggregate\CategoryTranslation;

use Shopware\Core\Content\Catalog\ORM\CatalogField;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Framework\ORM\EntityDefinition;

use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\LongTextField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Core\Framework\ORM\Field\StringField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\System\Language\LanguageDefinition;

class CategoryTranslationDefinition extends EntityDefinition
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
            new ManyToOneAssociationField('category', 'category_id', CategoryDefinition::class, false),
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, false),
        ]);
    }

    public static function getCollectionClass(): string
    {
        return CategoryTranslationCollection::class;
    }

    public static function getStructClass(): string
    {
        return CategoryTranslationStruct::class;
    }
}
