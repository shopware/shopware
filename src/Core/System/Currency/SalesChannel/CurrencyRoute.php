<?php declare(strict_types=1);

namespace Shopware\Core\System\Currency\SalesChannel;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\Currency\CurrencyCollection;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('inventory')]
class CurrencyRoute extends AbstractCurrencyRoute
{
    /**
     * @internal
     */
    public function __construct(private readonly SalesChannelRepository $currencyRepository)
    {
    }

    public function getDecorated(): AbstractCurrencyRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/currency', name: 'store-api.currency', methods: ['GET', 'POST'], defaults: ['_entity' => 'currency'])]
    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): CurrencyRouteResponse
    {
        /** @var CurrencyCollection $currencyCollection */
        $currencyCollection = $this->currencyRepository->search($criteria, $context)->getEntities();

        return new CurrencyRouteResponse($currencyCollection);
    }
}
