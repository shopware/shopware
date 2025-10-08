<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Routing\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @final
 *
 * @extends EntityCollection<ContentRouteEntity>
 */
#[Package('discovery')]
class ContentRouteCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'content_route_collection';
    }

    protected function getExpectedClass(): string
    {
        return ContentRouteEntity::class;
    }
}
