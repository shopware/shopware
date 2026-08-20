<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannel\AbstractContextHandoffGenerateRoute;
use Shopware\Core\System\SalesChannel\SalesChannel\AbstractContextHandoffRedeemRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Routing\ContextTokenSessionWriter;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put data
 */
#[Package('framework')]
#[Route(defaults: [
    PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID],
    PlatformRequest::ATTRIBUTE_NO_STORE => true,
    'XmlHttpRequest' => true,
])]
class ContextHandoffController extends StorefrontController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractContextHandoffGenerateRoute $generateRoute,
        private readonly AbstractContextHandoffRedeemRoute $redeemRoute,
        private readonly ContextTokenSessionWriter $contextTokenSessionWriter,
    ) {
    }

    #[Route(
        path: '/context/handoff/generate',
        name: 'frontend.context.handoff.generate',
        options: ['seo' => false],
        methods: [Request::METHOD_POST]
    )]
    public function generate(SalesChannelContext $context): JsonResponse
    {
        $handoff = $this->generateRoute->generate($context);

        return new JsonResponse([
            'token' => $handoff->getHandoffToken(),
            'expiresAt' => $handoff->getExpiresAt(),
        ]);
    }

    #[Route(
        path: '/context/handoff/redeem',
        name: 'frontend.context.handoff.redeem',
        options: ['seo' => false],
        methods: [Request::METHOD_POST]
    )]
    public function redeem(Request $request, RequestDataBag $data, SalesChannelContext $context): Response
    {
        $contextToken = $this->redeemRoute->redeem($data, $context)->getToken();

        $this->contextTokenSessionWriter->write($contextToken);
        $request->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $contextToken);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
