<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\ApiDefinition\ApiRoute;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\BrowserKit\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

if (!Feature::isActive('v6.5.0.0')) {
    /**
     * @internal
     *
     * @deprecated tag:v6.5.0 - Remove compatibility bridge to make parameters case insensitive
     * @see https://github.com/doctrine/annotations/issues/421
     * @Route(defaults={"_routeScope"={"store-api"}})
     */
    class TestAnnotationRoute extends AbstractStoreApiTestRoute
    {
        public function getDecorated(): AbstractStoreApiTestRoute
        {
            throw new DecorationPatternException(self::class);
        }

        /**
         * @Route("/store-api/test-annotation", name="store-api.test.annotation", Methods={"GET", "POST"})
         */
        public function load(Request $request, SalesChannelContext $context, Criteria $criteria): Response
        {
            return new Response('', 200, []);
        }
    }
} else {
    /**
     * @internal
     *
     * @deprecated tag:v6.5.0 - Remove compatibility bridge to make parameters case insensitive
     * @see https://github.com/doctrine/annotations/issues/421
     * @Route(defaults={"_routeScope"={"store-api"}})
     */
    class TestAnnotationRoute extends AbstractStoreApiTestRoute
    {
        public function getDecorated(): AbstractStoreApiTestRoute
        {
            throw new DecorationPatternException(self::class);
        }

        /**
         * @Route("/store-api/test-annotation", name="store-api.test.annotation", methods={"GET", "POST"})
         */
        public function load(Request $request, SalesChannelContext $context, Criteria $criteria): Response
        {
            return new Response('', 200, []);
        }
    }
}
