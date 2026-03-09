<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\ContentSystem\Rendering\ContentSystemDemoLayoutManager;
use Shopware\Storefront\ContentSystem\Rendering\ContentSystemDemoPageLoader;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]])]
#[Package('discovery')]
class ContentSystemDemoController extends StorefrontController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ContentSystemDemoPageLoader $pageLoader,
        private readonly ContentSystemDemoLayoutManager $layoutManager,
    ) {
    }

    #[Route(
        path: '/content-system/demo/{landingPageId}',
        name: 'frontend.content-system.demo.page',
        defaults: [PlatformRequest::ATTRIBUTE_HTTP_CACHE => false],
        methods: [Request::METHOD_GET]
    )]
    public function index(string $landingPageId, Request $request, SalesChannelContext $context): Response
    {
        $response = $this->renderStorefront(
            '@Storefront/storefront/page/content-system/demo.html.twig',
            $this->pageLoader->load($landingPageId, $request, $context)
        );

        // Allow embedding the demo preview inside the administration (same origin).
        $response->headers->set(PlatformRequest::HEADER_FRAME_OPTIONS, 'SAMEORIGIN');

        return $response;
    }

    #[Route(
        path: '/content-system/demo/layout/{landingPageId}',
        name: 'frontend.content-system.demo.layout',
        defaults: [PlatformRequest::ATTRIBUTE_HTTP_CACHE => false],
        methods: [Request::METHOD_GET]
    )]
    public function layout(string $landingPageId, Request $request, SalesChannelContext $context): JsonResponse
    {
        $skeletonResponse = $this->pageLoader->loadSkeleton($landingPageId, $request, $context);

        return new JsonResponse([
            'landingPageId' => $landingPageId,
            'salesChannelId' => $context->getSalesChannelId(),
            'endpoint' => $skeletonResponse['endpoint'],
            'payload' => $skeletonResponse['payload'],
            'error' => $skeletonResponse['error'],
        ]);
    }

    #[Route(
        path: '/content-system/demo/layout/{landingPageId}/move',
        name: 'frontend.content-system.demo.layout.move',
        defaults: [PlatformRequest::ATTRIBUTE_HTTP_CACHE => false],
        methods: [Request::METHOD_POST]
    )]
    public function moveLayoutElement(string $landingPageId, Request $request, SalesChannelContext $context): JsonResponse
    {
        try {
            $payload = $request->toArray();
            $slotPath = $payload['slotPath'] ?? [];
            $fromIndex = $payload['fromIndex'] ?? null;
            $direction = $payload['direction'] ?? null;

            if (!\is_array($slotPath) || !\array_is_list($slotPath)) {
                throw new \InvalidArgumentException('slotPath must be a JSON array.');
            }

            if (!\is_int($fromIndex)) {
                throw new \InvalidArgumentException('fromIndex must be an integer.');
            }

            if (!\is_string($direction)) {
                throw new \InvalidArgumentException('direction must be a string.');
            }

            $elementId = $this->layoutManager->moveElement($landingPageId, $context, $slotPath, $fromIndex, $direction);

            return new JsonResponse([
                'success' => true,
                'landingPageId' => $landingPageId,
                'selectedElementId' => $elementId,
            ]);
        } catch (\Throwable $exception) {
            return new JsonResponse([
                'success' => false,
                'error' => $exception->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
