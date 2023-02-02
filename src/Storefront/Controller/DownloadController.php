<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\Customer\SalesChannel\AbstractDownloadRoute;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @internal
 */
#[Package('customer-order')]
#[Route(defaults: ['_routeScope' => ['storefront']])]
class DownloadController extends StorefrontController
{
    /**
     * @internal
     */
    public function __construct(private readonly AbstractDownloadRoute $downloadRoute)
    {
    }

    #[Route(path: '/account/order/download/{orderId}/{downloadId}', name: 'frontend.account.order.single.download', defaults: ['_loginRequired' => true, '_loginRequiredAllowGuest' => true], methods: ['GET'])]
    public function downloadFile(Request $request, SalesChannelContext $context): Response
    {
        return $this->downloadRoute->load($request, $context);
    }
}
