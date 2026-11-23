<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\ProductAssignment\Api;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\ProductAssignment\SalesChannelProductAssigner;
use Shopware\Core\System\SalesChannel\SalesChannelException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Package('discovery')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
readonly class SalesChannelProductAssignmentController
{
    public function __construct(private SalesChannelProductAssigner $assigner)
    {
    }

    #[Route(
        path: '/api/_action/sales-channel/{salesChannelId}/product-assignment',
        name: 'api.action.sales_channel.product_assignment',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['product_visibility:create', 'product:read']],
        methods: ['POST']
    )]
    public function assign(
        string $salesChannelId,
        #[MapRequestPayload]
        SalesChannelProductAssignmentRequest $request,
        Context $context,
    ): JsonResponse {
        $this->ensureSourcePrivileges($request, $context);

        return new JsonResponse($this->assigner->assign($salesChannelId, $request, $context));
    }

    /**
     * Resolving a product group or category reveals and publishes the products behind it,
     * so the caller must be allowed to read the source it names.
     */
    private function ensureSourcePrivileges(SalesChannelProductAssignmentRequest $request, Context $context): void
    {
        $missing = [];

        if ($request->productStreamIds !== [] && !$context->isAllowed('product_stream:read')) {
            $missing[] = 'product_stream:read';
        }

        if ($request->categoryIds !== [] && !$context->isAllowed('category:read')) {
            $missing[] = 'category:read';
        }

        if ($missing !== []) {
            throw SalesChannelException::missingPrivileges($missing);
        }
    }
}
