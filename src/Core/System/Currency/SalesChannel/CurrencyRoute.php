<?php declare(strict_types=1);

namespace Shopware\Core\System\Currency\SalesChannel;

use Shopware\Core\Framework\Adapter\Cache\Event\AddCacheTagEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\Currency\CurrencyCollection;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('buyers-experience')]
class CurrencyRoute extends AbstractCurrencyRoute
{
    final public const ALL_TAG = 'currency-route';

    /**
     * @internal
     */
    public function __construct(
        private readonly SalesChannelRepository $currencyRepository,
        private readonly EventDispatcherInterface $dispatcher
    ) {
    }

    public function getDecorated(): AbstractCurrencyRoute
    {
        throw new DecorationPatternException(self::class);
    }

    public static function buildName(string $salesChannelId): string
    {
        return 'currency-route-' . $salesChannelId;
    }

    #[Route(path: '/store-api/currency', name: 'store-api.currency', methods: ['GET', 'POST'], defaults: ['_entity' => 'currency'])]
    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): CurrencyRouteResponse
    {
        $this->dispatcher->dispatch(new AddCacheTagEvent(
            self::buildName($context->getSalesChannelId()),
            self::ALL_TAG
        ));

        /** @var CurrencyCollection $currencyCollection */
        $currencyCollection = $this->currencyRepository->search($criteria, $context)->getEntities();

        return new CurrencyRouteResponse($currencyCollection);
    }
}
