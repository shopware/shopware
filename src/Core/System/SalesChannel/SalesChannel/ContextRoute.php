<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\SalesChannel;

use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @package core
 *
 * @Route(defaults={"_routeScope"={"store-api"}})
 */
class ContextRoute extends AbstractContextRoute
{
    public function getDecorated(): AbstractContextRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Since("6.3.0.0")
     * @Route("/store-api/context", name="store-api.context", methods={"GET"})
     */
    public function load(SalesChannelContext $context): ContextLoadRouteResponse
    {
        return new ContextLoadRouteResponse($context);
    }
}
