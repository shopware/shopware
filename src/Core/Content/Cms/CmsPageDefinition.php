<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms;

use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsPageTranslation\CmsPageTranslationDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionDefinition;
use Shopware\Core\Content\LandingPage\LandingPageDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
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
use Shopware\Core\Framework\Feature;

class CmsPageDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'cms_page';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return CmsPageEntity::class;
    }

    public function getCollectionClass(): string
    {
        return CmsPageCollection::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        $collection = new FieldCollection([
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

            (new OneToManyAssociationField('sections', CmsSectionDefinition::class, 'cms_page_id'))->addFlags(new CascadeDelete()),
            new TranslationsAssociationField(CmsPageTranslationDefinition::class, 'cms_page_id'),

            new ManyToOneAssociationField('previewMedia', 'preview_media_id', MediaDefinition::class, 'id', false),

            (new OneToManyAssociationField('categories', CategoryDefinition::class, 'cms_page_id'))->addFlags(new RestrictDelete()),
        ]);

        if (Feature::isActive('FEATURE_NEXT_10078')) {
            $collection->add(
                (new OneToManyAssociationField('products', ProductDefinition::class, 'cms_page_id'))->addFlags(new RestrictDelete())
            );
        }

        if (Feature::isActive('FEATURE_NEXT_12032')) {
            $collection->add(
                (new OneToManyAssociationField('landingPages', LandingPageDefinition::class, 'cms_page_id'))->addFlags(new RestrictDelete())
            );
        }

        return $collection;
    }
}
