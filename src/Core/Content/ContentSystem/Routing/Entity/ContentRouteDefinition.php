<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Routing\Entity;

use Shopware\Core\Content\ContentSystem\Layout\Entity\ContentLayoutDefinition;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

/**
 * @internal
 */
#[Package('discovery')]
class ContentRouteDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'content_route';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ContentRouteEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ContentRouteCollection::class;
    }

    public function since(): ?string
    {
        return '6.7.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->addFlags(new ApiAware(), new Required()),
            (new StringField('url_pattern', 'urlPattern'))->addFlags(new ApiAware(), new Required()),
            (new JsonField('parameter_binding', 'parameterBinding'))->addFlags(new ApiAware(AdminApiSource::class), new Required()),
            (new FkField('layout_id', 'layoutId', ContentLayoutDefinition::class))->addFlags(new ApiAware(AdminApiSource::class)),
            (new JsonField('layout_cascade', 'layoutCascade'))->addFlags(new ApiAware(AdminApiSource::class)),
            (new IntField('priority', 'priority'))->addFlags(new ApiAware(AdminApiSource::class)),
            (new JsonField('overrides', 'overrides'))->addFlags(new ApiAware(AdminApiSource::class)),
            (new BoolField('active', 'active'))->addFlags(new ApiAware(AdminApiSource::class)),

            (new ManyToOneAssociationField('layout', 'layout_id', ContentLayoutDefinition::class, 'id', false))->addFlags(new ApiAware(AdminApiSource::class)),

            (new ManyToManyAssociationField(
                'salesChannels',
                SalesChannelDefinition::class,
                ContentRouteSalesChannelDefinition::class,
                'content_route_id',
                'sales_channel_id'
            ))->addFlags(new ApiAware(AdminApiSource::class)),
        ]);
    }
}
