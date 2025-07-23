<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put data
 */
#[Route(defaults: ['_routeScope' => ['storefront']])]
#[Package('discovery')]
class CmsV2Controller extends StorefrontController
{
    #[Route(path: '/page/cms-v2/{id}', name: 'frontend.cms_v2.page.full', defaults: ['XmlHttpRequest' => true, '_httpCache' => true], methods: ['GET', 'POST'])]
    public function pageFull(string $id, Request $request, SalesChannelContext $salesChannelContext): Response
    {
        return new JsonResponse(file_get_contents('./product-listing-for-cms-v2'));
    }
}
