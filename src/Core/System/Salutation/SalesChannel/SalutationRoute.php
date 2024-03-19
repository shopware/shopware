<?php declare(strict_types=1);

namespace Shopware\Core\System\Salutation\SalesChannel;

use Shopware\Core\Framework\Adapter\Cache\Event\AddCacheTagEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Salutation\SalutationCollection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('buyers-experience')]
class SalutationRoute extends AbstractSalutationRoute
{
    /**
     * @internal
     *
     * @param SalesChannelRepository<SalutationCollection> $salutationRepository
     */
    public function __construct(
        private readonly SalesChannelRepository $salutationRepository,
        private readonly EventDispatcherInterface $dispatcher
    ) {
    }

    public static function buildName(): string
    {
        return 'salutation-route';
    }

    public function getDecorated(): AbstractSalutationRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/salutation', name: 'store-api.salutation', methods: ['GET', 'POST'], defaults: ['_entity' => 'salutation'])]
    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): SalutationRouteResponse
    {
        $this->dispatcher->dispatch(new AddCacheTagEvent(self::buildName()));

        return new SalutationRouteResponse($this->salutationRepository->search($criteria, $context));
    }
}
