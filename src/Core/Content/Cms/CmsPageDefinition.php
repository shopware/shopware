<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms;

use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsPageTranslation\CmsPageTranslationDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LockedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class CmsPageDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'cms_page';
    }

    public function getEntityClass(): string
    {
        return CmsPageEntity::class;
    }

    public function getCollectionClass(): string
    {
        return CmsPageCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),

            new TranslatedField('name'),
            (new StringField('type', 'type'))->addFlags(new Required()),
            new StringField('entity', 'entity'),
            new JsonField('config', 'config', [
                new StringField('background_color', 'backgroundColor'),
            ]),

            new FkField('preview_media_id', 'previewMediaId', MediaDefinition::class),

            new TranslatedField('customFields'),

            new LockedField(),

            (new OneToManyAssociationField('blocks', CmsBlockDefinition::class, 'cms_page_id'))->addFlags(new CascadeDelete()),
            new TranslationsAssociationField(CmsPageTranslationDefinition::class, 'cms_page_id'),

            new ManyToOneAssociationField('previewMedia', 'preview_media_id', MediaDefinition::class, 'id', true),

            (new OneToManyAssociationField('categories', CategoryDefinition::class, 'cms_page_id'))->addFlags(new RestrictDelete()),
        ]);
    }
}
