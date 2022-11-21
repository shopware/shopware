<?php declare(strict_types=1);

namespace Shopware\Core\System\Salutation\SalesChannel;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\Entity;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(defaults={"_routeScope"={"store-api"}})
 */
class SalutationRoute extends AbstractSalutationRoute
{
    /**
     * @var SalesChannelRepository
     */
    private $salesChannelRepository;

    /**
     * @internal
     */
    public function __construct(
        SalesChannelRepository $salesChannelRepository
    ) {
        $this->salesChannelRepository = $salesChannelRepository;
    }

    public function getDecorated(): AbstractSalutationRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Since("6.2.0.0")
     * @Entity("salutation")
     * @Route(path="/store-api/salutation", name="store-api.salutation", methods={"GET", "POST"})
     */
    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): SalutationRouteResponse
    {
        $criteria->addFilter(new NotFilter('or', [
            new EqualsFilter('id', Defaults::SALUTATION),
        ]));

        return new SalutationRouteResponse($this->salesChannelRepository->search($criteria, $context));
    }
}
