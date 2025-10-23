<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Aggregate\CategoryContentLayout;

use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\ContentSystem\Layout\Entity\ContentLayoutDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

#[Package('discovery')]
class CategoryContentLayoutDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'category_content_layout';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return CategoryContentLayoutEntity::class;
    }

    public function getCollectionClass(): string
    {
        return CategoryContentLayoutCollection::class;
    }

    protected function getParentDefinitionClass(): ?string
    {
        return CategoryDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),

            (new FkField('category_id', 'categoryId', CategoryDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new ReferenceVersionField(CategoryDefinition::class))->addFlags(new Required()),

            (new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class))->addFlags(new ApiAware()),
            (new FkField('content_layout_id', 'contentLayoutId', ContentLayoutDefinition::class))->addFlags(new ApiAware(), new Required()),

            new ManyToOneAssociationField('category', 'category_id', CategoryDefinition::class, 'id', false),
            new ManyToOneAssociationField('salesChannel', 'sales_channel_id', SalesChannelDefinition::class, 'id', false),
            new ManyToOneAssociationField('contentLayout', 'content_layout_id', ContentLayoutDefinition::class, 'id', false),
        ]);
    }
}
