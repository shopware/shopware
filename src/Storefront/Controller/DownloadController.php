<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\Customer\SalesChannel\AbstractDownloadRoute;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put data
 */
#[Package('checkout')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]])]
class DownloadController extends StorefrontController
{
    /**
     * @internal
     */
    public function __construct(private readonly AbstractDownloadRoute $downloadRoute)
    {
    }

    #[Route(path: '/account/order/download/{orderId}/{downloadId}', name: 'frontend.account.order.single.download', methods: ['GET'])]
    public function downloadFile(Request $request, SalesChannelContext $context): Response
    {
        if (!$context->getCustomer()) {
            return $this->redirectToRoute(
                'frontend.account.order.single.page',
                [
                    'deepLinkCode' => $request->get('deepLinkCode', false),
                ]
            );
        }

        return $this->downloadRoute->load($request, $context);
    }
}
