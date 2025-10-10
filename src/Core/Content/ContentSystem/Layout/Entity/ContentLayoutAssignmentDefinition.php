<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Entity;

use Shopware\Core\Content\ContentSystem\Routing\Entity\ContentRouteDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

/**
 * @internal
 */
#[Package('discovery')]
class ContentLayoutAssignmentDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'content_layout_assignment';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ContentLayoutAssignmentEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ContentLayoutAssignmentCollection::class;
    }

    public function since(): ?string
    {
        return '6.7.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
            (new FkField('route_id', 'routeId', ContentRouteDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new StringField('entity_type', 'entityType', 50))->addFlags(new ApiAware()),
            (new IdField('entity_id', 'entityId'))->addFlags(new ApiAware()),
            (new StringField('association_path', 'associationPath', 255))->addFlags(new ApiAware()),
            (new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class))->addFlags(new ApiAware()),
            (new FkField('layout_id', 'layoutId', ContentLayoutDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new IntField('priority', 'priority'))->addFlags(new ApiAware()),

            (new ManyToOneAssociationField('route', 'route_id', ContentRouteDefinition::class, 'id', false))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('layout', 'layout_id', ContentLayoutDefinition::class, 'id', false))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('salesChannel', 'sales_channel_id', SalesChannelDefinition::class, 'id', false))->addFlags(new ApiAware()),
        ]);
    }
}
