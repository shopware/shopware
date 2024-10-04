<?php
declare(strict_types=1);

namespace Shopware\Core\Content\Category\SalesChannel;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('inventory')]
class SalesChannelEntrypointController extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SalesChannelEntrypointService $entrypointService,
        private readonly EntityRepository $salesChannelRepository
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

    #[Route(path: '/api/_action/sales-channel/{salesChannelId}/entrypoint', name: 'api.action.sales-channel.entrypoint.getList', defaults: ['_acl' => ['sales_channel:read']], methods: ['GET'])]
    public function getSalesChannelEntrypoint(string $salesChannelId, Context $context): JsonResponse
    {
        $salesChannel = $this->salesChannelRepository->search(new Criteria([$salesChannelId]), $context)->first();
        if (!$salesChannel instanceof SalesChannelEntity) {
            return new JsonResponse([]);
        }
        $entrypoints = $this->entrypointService->getCustomEntrypoints($salesChannel);

        return new JsonResponse($entrypoints);
    }
}
