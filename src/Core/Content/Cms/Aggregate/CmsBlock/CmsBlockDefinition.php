<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\Aggregate\CmsBlock;

use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotDefinition;
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
class CmsBlockDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'cms_block';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return CmsBlockEntity::class;
    }

    public function getCollectionClass(): string
    {
        return CmsBlockCollection::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function getParentDefinitionClass(): ?string
    {
        return CmsSectionDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of CMS block.'),
            (new VersionField())->addFlags(new ApiAware()),
            (new ReferenceVersionField(CmsSectionDefinition::class))->addFlags(new Required(), new ApiAware()),

            (new IntField('position', 'position'))->addFlags(new ApiAware(), new Required())->setDescription('Order of the block indicated by number like 0, 1, 2,...'),
            (new StringField('type', 'type'))->addFlags(new ApiAware(), new Required())->setDescription('Type of block can be \'image`, `text`, \'product-listing`, `image-two-column`, etc.'),
            new LockedField(),
            (new StringField('name', 'name'))->addFlags(new ApiAware())->setDescription('Unique name of the CMS Block.'),
            (new StringField('section_position', 'sectionPosition'))->addFlags(new ApiAware())->setDescription('Position of the section. It can either be `main` or `sidebar`.'),
            (new StringField('margin_top', 'marginTop'))->addFlags(new ApiAware())->setDescription('Defines the margin area on the top of an element.'),
            (new StringField('margin_bottom', 'marginBottom'))->addFlags(new ApiAware())->setDescription('Defines for the margin area on the bottom of an element.'),
            (new StringField('margin_left', 'marginLeft'))->addFlags(new ApiAware())->setDescription('Defines for the margin area on the left of an element.'),
            (new StringField('margin_right', 'marginRight'))->addFlags(new ApiAware())->setDescription('Defines the margin area on the right of an element.'),
            (new StringField('background_color', 'backgroundColor'))->addFlags(new ApiAware())->setDescription('Defines the background color of an element.'),
            (new FkField('background_media_id', 'backgroundMediaId', MediaDefinition::class))->addFlags(new ApiAware())->setDescription('Unique identity of background media.'),
            (new StringField('background_media_mode', 'backgroundMediaMode'))->addFlags(new ApiAware())->setDescription('Background media mode accept values `cover`, `auto`, `contain`.'),
            (new StringField('css_class', 'cssClass'))->addFlags(new ApiAware())->setDescription('One or more CSS classes added and separated by spaces.'),
            (new JsonField('visibility', 'visibility', [
                new BoolField('mobile', 'mobile'),
                new BoolField('desktop', 'desktop'),
                new BoolField('tablet', 'tablet'),
            ]))->addFlags(new ApiAware())->setDescription('When `true`, CMS layout can be viewed in tablet mode.'),

            (new FkField('cms_section_id', 'sectionId', CmsSectionDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of section.'),
            new ManyToOneAssociationField('section', 'cms_section_id', CmsSectionDefinition::class, 'id', false),
            (new ManyToOneAssociationField('backgroundMedia', 'background_media_id', MediaDefinition::class, 'id', false))->addFlags(new ApiAware()),

            (new OneToManyAssociationField('slots', CmsSlotDefinition::class, 'cms_block_id'))->addFlags(new ApiAware(), new CascadeDelete()),
            (new CustomFields())->addFlags(new ApiAware())->setDescription('Additional fields that offer a possibility to add own fields for the different program-areas.'),
        ]);
    }
}
