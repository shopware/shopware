<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\Aggregate\CmsBlock;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotDefinition;
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

class CmsBlockDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'cms_block';
    }

    public function getEntityClass(): string
    {
        return CmsBlockEntity::class;
    }

    public function getCollectionClass(): string
    {
        return CmsBlockCollection::class;
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
            new StringField('margin_top', 'marginTop'),
            new StringField('margin_bottom', 'marginBottom'),
            new StringField('margin_left', 'marginLeft'),
            new StringField('margin_right', 'marginRight'),
            new StringField('background_color', 'backgroundColor'),
            new FkField('background_media_id', 'backgroundMediaId', MediaDefinition::class),
            new StringField('background_media_mode', 'backgroundMediaMode'),
            new StringField('css_class', 'cssClass'),

            (new FkField('cms_page_id', 'pageId', CmsPageDefinition::class))->addFlags(new Required()),
            new ManyToOneAssociationField('page', 'cms_page_id', CmsPageDefinition::class, 'id', false),

            new ManyToOneAssociationField('backgroundMedia', 'background_media_id', MediaDefinition::class, 'id', false),

            (new OneToManyAssociationField('slots', CmsSlotDefinition::class, 'cms_block_id'))->addFlags(new CascadeDelete()),

            new CustomFields(),
        ]);
    }
}
