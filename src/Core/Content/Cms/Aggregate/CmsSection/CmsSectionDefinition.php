<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\Aggregate\CmsSection;

use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockDefinition;
use Shopware\Core\Content\Cms\CmsPageDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LockedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;

#[Package('discovery')]
class CmsSectionDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'cms_section';

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
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of CMS section.'),
            new VersionField(),
            (new ReferenceVersionField(CmsPageDefinition::class))->addFlags(new Required(), new ApiAware()),

            (new IntField('position', 'position'))->addFlags(new ApiAware(), new Required())->setDescription('Position of occurrence of each section denoted by numerical values 0, 1, 2...'),
            (new StringField('type', 'type'))->addFlags(new ApiAware(), new Required())->setDescription('Types of sections can be `sidebar` or `fullwidth`.'),
            new LockedField(),
            (new StringField('name', 'name'))->addFlags(new ApiAware())->setDescription('Name of the CMS section defined.'),
            (new StringField('sizing_mode', 'sizingMode'))->addFlags(new ApiAware())->setDescription('Sizing mode can be `boxed` or `full_width`.'),
            (new StringField('mobile_behavior', 'mobileBehavior'))->addFlags(new ApiAware())->setDescription('Hides the sidebar on mobile viewports. It can hold values such as \'mobile\', \'wrap\', any other string or be unset.'),
            (new StringField('background_color', 'backgroundColor'))->addFlags(new ApiAware())->setDescription('Background color of CMS page.'),
            (new FkField('background_media_id', 'backgroundMediaId', MediaDefinition::class))->addFlags(new ApiAware())->setDescription('Unique identity of CMS section\'s background media.'),
            (new StringField('background_media_mode', 'backgroundMediaMode'))->addFlags(new ApiAware())->setDescription('Background media mode can be `cover`, `auto` or `contain`.'),
            (new StringField('css_class', 'cssClass'))->addFlags(new ApiAware())->setDescription('One or more CSS classes added and separated by spaces.'),
            (new FkField('cms_page_id', 'pageId', CmsPageDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of page where CMS section is defined.'),
            (new JsonField('visibility', 'visibility', [
                new BoolField('mobile', 'mobile'),
                new BoolField('desktop', 'desktop'),
                new BoolField('tablet', 'tablet'),
            ]))->addFlags(new ApiAware())->setDescription('When `true`, CMS layout can be viewed in tablet mode.'),
            (new ManyToOneAssociationField('page', 'cms_page_id', CmsPageDefinition::class, 'id', false))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('backgroundMedia', 'background_media_id', MediaDefinition::class, 'id', false))->addFlags(new ApiAware()),
            (new OneToManyAssociationField('blocks', CmsBlockDefinition::class, 'cms_section_id'))->addFlags(new ApiAware(), new CascadeDelete()),
            (new CustomFields())->addFlags(new ApiAware())->setDescription('Additional fields that offer a possibility to add own fields for the different program-areas.'),
        ]);
    }
}
