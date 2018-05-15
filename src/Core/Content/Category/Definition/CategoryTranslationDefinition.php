<?php declare(strict_types=1);

namespace Shopware\Content\Category\Definition;

use Shopware\Content\Category\Collection\CategoryTranslationBasicCollection;
use Shopware\Content\Category\Collection\CategoryTranslationDetailCollection;
use Shopware\Content\Category\Event\CategoryTranslation\CategoryTranslationDeletedEvent;
use Shopware\Content\Category\Event\CategoryTranslation\CategoryTranslationWrittenEvent;
use Shopware\Content\Category\Repository\CategoryTranslationRepository;
use Shopware\Content\Category\Struct\CategoryTranslationBasicStruct;
use Shopware\Content\Category\Struct\CategoryTranslationDetailStruct;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\CatalogField;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\LongTextField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\ReferenceVersionField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\VersionField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Application\Language\Definition\LanguageDefinition;

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
