<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;

class StoreApiRouteScope extends SalesChannelApiRouteScope
{
    public const ID = DefinitionService::STORE_API;

    /**
     * @var string[]
     */
    protected $allowedPaths = [DefinitionService::STORE_API];
}
