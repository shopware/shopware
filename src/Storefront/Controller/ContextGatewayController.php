<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Framework\Gateway\Context\SalesChannel\AbstractContextGatewayRoute;
use Shopware\Core\Framework\Gateway\GatewayException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put datas
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]])]
#[Package('checkout')]
class ContextGatewayController extends StorefrontController
{
    public function __construct(
        private readonly AbstractContextGatewayRoute $contextGatewayRoute,
        private readonly CartService $cartService,
    ) {
    }

    #[Route('/gateway/context', name: 'frontend.gateway.context', defaults: ['XmlHttpRequest' => true], methods: ['GET', 'POST'])]
    public function gateway(Request $request, SalesChannelContext $context): Response
    {
        $cart = $this->cartService->getCart($context->getToken(), $context);

        try {
            $response = $this->contextGatewayRoute->load($request, $cart, $context);
        } catch (GatewayException $e) {
            if ($e->getErrorCode() === GatewayException::CUSTOMER_MESSAGE) {
                $this->addFlash(self::DANGER, $e->getMessage());
            }

            return new JsonResponse(status: Response::HTTP_BAD_REQUEST);
        }

        return $response;
    }
}
