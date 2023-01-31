<?php declare(strict_types=1);

namespace Shopware\Core\System\Salutation\SalesChannel;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('customer-order')]
class SalutationRoute extends AbstractSalutationRoute
{
    /**
     * @internal
     */
    public function __construct(private readonly SalesChannelRepository $salesChannelRepository)
    {
    }

    public function getDecorated(): AbstractSalutationRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/salutation', name: 'store-api.salutation', methods: ['GET', 'POST'], defaults: ['_entity' => 'salutation'])]
    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): SalutationRouteResponse
    {
        return new SalutationRouteResponse($this->salesChannelRepository->search($criteria, $context));
    }
}
