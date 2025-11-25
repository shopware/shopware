<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Shopware\Storefront\Page\Robots\RobotsPageLoader;
use Shopware\Tests\Integration\Storefront\Controller\RobotsControllerTest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 *
 * we use both API and Storefront route scope here, so that the robots.txt can be accessed
 * via all sales channel domains (+ path routing) + all top level domains without any sales channel domain
 *
 * @see RobotsControllerTest
 *
 * @CodeCoverageIgnore -> covered by integration tests
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID, StorefrontRouteScope::ID], 'auth_required' => false])]
#[Package('framework')]
class RobotsController extends StorefrontController
{
    public function __construct(private readonly RobotsPageLoader $robotsPageLoader)
    {
    }

    #[Route(path: '/robots.txt', name: 'frontend.robots.txt', defaults: ['_format' => 'txt', '_httpCache' => true], methods: ['GET'])]
    public function robotsTxt(Request $request, Context $context): Response
    {
        $page = $this->robotsPageLoader->load($request, $context);

        $response = $this->render('@Storefront/storefront/page/robots/robots.txt.twig', ['page' => $page]);
        $response->headers->set('content-type', 'text/plain; charset=utf-8');

        return $response;
    }
}
