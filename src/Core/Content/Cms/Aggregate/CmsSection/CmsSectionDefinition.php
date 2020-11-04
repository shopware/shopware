<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\Aggregate\CmsSection;

use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockDefinition;
use Shopware\Core\Content\Cms\CmsPageDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LockedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class CmsSectionDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'cms_section';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return CmsSectionEntity::class;
    }

    public function getCollectionClass(): string
    {
        return CmsSectionCollection::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function getParentDefinitionClass(): ?string
    {
        return CmsPageDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),

            (new IntField('position', 'position'))->addFlags(new Required()),
            (new StringField('type', 'type'))->addFlags(new Required()),

            new LockedField(),

            new StringField('name', 'name'),
            new StringField('sizing_mode', 'sizingMode'),
            new StringField('mobile_behavior', 'mobileBehavior'),
            new StringField('background_color', 'backgroundColor'),
            new FkField('background_media_id', 'backgroundMediaId', MediaDefinition::class),
            new StringField('background_media_mode', 'backgroundMediaMode'),
            new StringField('css_class', 'cssClass'),

            (new FkField('cms_page_id', 'pageId', CmsPageDefinition::class))->addFlags(new Required()),
            new ManyToOneAssociationField('page', 'cms_page_id', CmsPageDefinition::class, 'id', false),

            new ManyToOneAssociationField('backgroundMedia', 'background_media_id', MediaDefinition::class, 'id', false),

            (new OneToManyAssociationField('blocks', CmsBlockDefinition::class, 'cms_section_id'))->addFlags(new CascadeDelete()),

            new CustomFields(),
        ]);
    }
}
