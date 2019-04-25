<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms;

use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsPageTranslation\CmsPageTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class CmsPageDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'cms_page';
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return CmsPageTranslationDefinition::class;
    }

    public static function getEntityClass(): string
    {
        return CmsPageEntity::class;
    }

    public static function getCollectionClass(): string
    {
        return CmsPageCollection::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),

            new TranslatedField('name'),
            (new StringField('type', 'type'))->addFlags(new Required()),
            new StringField('entity', 'entity'),
            new JsonField('config', 'config', [
                new StringField('background_color', 'backgroundColor'),
            ]),

            new TranslatedField('attributes'),

            (new OneToManyAssociationField('blocks', CmsBlockDefinition::class, 'cms_page_id'))->addFlags(new CascadeDelete()),
            new TranslationsAssociationField(CmsPageTranslationDefinition::class, 'cms_page_id'),

            new OneToManyAssociationField('categories', CategoryDefinition::class, 'cms_page_id'),

            new CreatedAtField(),
            new UpdatedAtField(),
        ]);
    }
}
