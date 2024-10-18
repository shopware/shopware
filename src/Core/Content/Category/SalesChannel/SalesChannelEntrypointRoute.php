<?php
declare(strict_types=1);

namespace Shopware\Core\Content\Category\SalesChannel;

use Shopware\Core\Framework\Adapter\Cache\Event\AddCacheTagEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('inventory')]
class SalesChannelEntrypointRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SalesChannelEntrypointService $entrypointService,
        private readonly EventDispatcherInterface $dispatcher,
    ) {
    }

    public static function buildName(string $id): string
    {
        return 'sales-channel-entrypoint-route-' . $id;
    }

    public function getDecorated(): AbstractNavigationRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/entry-point', name: 'store-api.entrypoint', methods: ['GET', 'POST'])]
    public function load(
        SalesChannelContext $context
    ): SalesChannelEntrypointRouteResponse {
        $tags = [
            self::buildName($context->getSalesChannelId()),
        ];

        $this->dispatcher->dispatch(new AddCacheTagEvent(...$tags));

        $entryPointIds = $this->entrypointService->getCustomEntrypointIds($context->getSalesChannel(), $context);

        return new SalesChannelEntrypointRouteResponse($entryPointIds);
    }
}
