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

#[Package('content')]
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
        $collection = new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new IntField('position', 'position'))->addFlags(new ApiAware(), new Required()),
            (new StringField('type', 'type'))->addFlags(new ApiAware(), new Required()),
            new LockedField(),
            (new StringField('name', 'name'))->addFlags(new ApiAware()),
            (new StringField('section_position', 'sectionPosition'))->addFlags(new ApiAware()),
            (new StringField('margin_top', 'marginTop'))->addFlags(new ApiAware()),
            (new StringField('margin_bottom', 'marginBottom'))->addFlags(new ApiAware()),
            (new StringField('margin_left', 'marginLeft'))->addFlags(new ApiAware()),
            (new StringField('margin_right', 'marginRight'))->addFlags(new ApiAware()),
            (new StringField('background_color', 'backgroundColor'))->addFlags(new ApiAware()),
            (new FkField('background_media_id', 'backgroundMediaId', MediaDefinition::class))->addFlags(new ApiAware()),
            (new StringField('background_media_mode', 'backgroundMediaMode'))->addFlags(new ApiAware()),
            (new StringField('css_class', 'cssClass'))->addFlags(new ApiAware()),
            (new JsonField('visibility', 'visibility', [
                new BoolField('mobile', 'mobile'),
                new BoolField('desktop', 'desktop'),
                new BoolField('tablet', 'tablet'),
            ]))->addFlags(new ApiAware()),

            (new FkField('cms_section_id', 'sectionId', CmsSectionDefinition::class))->addFlags(new ApiAware(), new Required()),
            new ManyToOneAssociationField('section', 'cms_section_id', CmsSectionDefinition::class, 'id', false),
            (new ManyToOneAssociationField('backgroundMedia', 'background_media_id', MediaDefinition::class, 'id', false))->addFlags(new ApiAware()),

            (new OneToManyAssociationField('slots', CmsSlotDefinition::class, 'cms_block_id'))->addFlags(new ApiAware(), new CascadeDelete()),
            (new CustomFields())->addFlags(new ApiAware()),
        ]);

        $collection->add((new VersionField())->addFlags(new ApiAware()));
        $collection->add((new ReferenceVersionField(CmsSectionDefinition::class))->addFlags(new Required(), new ApiAware()));

        return $collection;
    }
}
