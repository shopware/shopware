<?php declare(strict_types=1);

namespace Shopware\Content\Category\Aggregate\CategoryTranslation;

use Shopware\Content\Category\Aggregate\CategoryTranslation\Collection\CategoryTranslationBasicCollection;
use Shopware\Content\Category\Aggregate\CategoryTranslation\Collection\CategoryTranslationDetailCollection;
use Shopware\Content\Category\Aggregate\CategoryTranslation\Event\CategoryTranslationDeletedEvent;
use Shopware\Content\Category\Aggregate\CategoryTranslation\Event\CategoryTranslationWrittenEvent;
use Shopware\Content\Category\CategoryDefinition;

use Shopware\Content\Category\Aggregate\CategoryTranslation\Struct\CategoryTranslationBasicStruct;
use Shopware\Content\Category\Aggregate\CategoryTranslation\Struct\CategoryTranslationDetailStruct;
use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\EntityExtensionInterface;
use Shopware\Framework\ORM\Field\CatalogField;
use Shopware\Framework\ORM\Field\FkField;
use Shopware\Framework\ORM\Field\LongTextField;
use Shopware\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Framework\ORM\Field\StringField;

use Shopware\Framework\ORM\FieldCollection;
use Shopware\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Framework\ORM\Write\Flag\Required;
use Shopware\Application\Language\LanguageDefinition;

class CategoryTranslationDefinition extends EntityDefinition
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
        return 'category_translation';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
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

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return CategoryTranslationRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return CategoryTranslationBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return CategoryTranslationDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return CategoryTranslationWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return CategoryTranslationBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }

    public static function getDetailStructClass(): string
    {
        return CategoryTranslationDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return CategoryTranslationDetailCollection::class;
    }
}
