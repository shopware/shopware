<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\Aggregate\CmsBlock;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotDefinition;
use Shopware\Core\Content\Cms\CmsPageDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class CmsBlockDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'cms_block';
    }

    public static function getEntityClass(): string
    {
        return CmsBlockEntity::class;
    }

    public static function getCollectionClass(): string
    {
        return CmsBlockCollection::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),

            (new IntField('position', 'position'))->addFlags(new Required()),
            (new StringField('type', 'type'))->addFlags(new Required()),

            new JsonField('config', 'config', [
                new StringField('name', 'name'),
                new StringField('sizingMode', 'sizingMode'),
                new StringField('marginTop', 'marginTop'),
                new StringField('marginBottom', 'marginBottom'),
                new StringField('marginLeft', 'marginLeft'),
                new StringField('marginRight', 'marginRight'),
                new StringField('backgroundColor', 'backgroundColor'),
                new StringField('backgroundMode', 'backgroundMode'),
                new StringField('cssClass', 'cssClass'),
            ]),

            (new FkField('cms_page_id', 'pageId', CmsPageDefinition::class))->addFlags(new Required()),
            new ManyToOneAssociationField('page', 'cms_page_id', CmsPageDefinition::class, 'id', false),

            (new OneToManyAssociationField('slots', CmsSlotDefinition::class, 'cms_block_id'))->addFlags(new CascadeDelete()),

            new CustomFields(),
        ]);
    }
}
