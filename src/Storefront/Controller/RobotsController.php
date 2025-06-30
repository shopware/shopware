<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Page\Robots\RobotsPageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Route(defaults: ['_routeScope' => ['api'], 'auth_required' => false])]
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
