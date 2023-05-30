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

#[Package('content')]
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
        $collection = new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new IntField('position', 'position'))->addFlags(new ApiAware(), new Required()),
            (new StringField('type', 'type'))->addFlags(new ApiAware(), new Required()),
            new LockedField(),
            (new StringField('name', 'name'))->addFlags(new ApiAware()),
            (new StringField('sizing_mode', 'sizingMode'))->addFlags(new ApiAware()),
            (new StringField('mobile_behavior', 'mobileBehavior'))->addFlags(new ApiAware()),
            (new StringField('background_color', 'backgroundColor'))->addFlags(new ApiAware()),
            (new FkField('background_media_id', 'backgroundMediaId', MediaDefinition::class))->addFlags(new ApiAware()),
            (new StringField('background_media_mode', 'backgroundMediaMode'))->addFlags(new ApiAware()),
            (new StringField('css_class', 'cssClass'))->addFlags(new ApiAware()),
            (new FkField('cms_page_id', 'pageId', CmsPageDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new JsonField('visibility', 'visibility', [
                new BoolField('mobile', 'mobile'),
                new BoolField('desktop', 'desktop'),
                new BoolField('tablet', 'tablet'),
            ]))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('page', 'cms_page_id', CmsPageDefinition::class, 'id', false))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('backgroundMedia', 'background_media_id', MediaDefinition::class, 'id', false))->addFlags(new ApiAware()),
            (new OneToManyAssociationField('blocks', CmsBlockDefinition::class, 'cms_section_id'))->addFlags(new ApiAware(), new CascadeDelete()),
            (new CustomFields())->addFlags(new ApiAware()),
        ]);

        $collection->add(new VersionField());
        $collection->add((new ReferenceVersionField(CmsPageDefinition::class))->addFlags(new Required(), new ApiAware()));

        return $collection;
    }
}
