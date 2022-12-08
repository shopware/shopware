<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\SalesChannel;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\Entity;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @package sales-channel
 *
 * @Route(defaults={"_routeScope"={"store-api"}})
 */
class SeoUrlRoute extends AbstractSeoUrlRoute
{
    /**
     * @var SalesChannelRepository
     */
    private $salesChannelRepository;

    /**
     * @internal
     */
    public function __construct(SalesChannelRepository $salesChannelRepository)
    {
        $this->salesChannelRepository = $salesChannelRepository;
    }

    public function getDecorated(): AbstractSeoUrlRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Since("6.2.0.0")
     * @Entity("seo_url")
     * @Route("/store-api/seo-url", name="store-api.seo.url", methods={"GET", "POST"})
     */
    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): SeoUrlRouteResponse
    {
        return new SeoUrlRouteResponse($this->salesChannelRepository->search($criteria, $context));
    }
}
