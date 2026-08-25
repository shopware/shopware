<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\ContentSystem\Api\ContentPreviewPageBuilder;
use Shopware\Core\Framework\ContentSystem\Api\ContentPreviewPayloadStore;
use Shopware\Core\Framework\ContentSystem\Output\Struct\ContentPage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]])]
class ContentSystemPreviewController extends StorefrontController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ContentPreviewPayloadStore $payloadStore,
        private readonly ContentPreviewPageBuilder $previewPageBuilder,
        private readonly Connection $connection,
    ) {
    }

    #[Route(path: '/content-system/preview/{token}', name: 'frontend.content-system.preview', methods: [Request::METHOD_GET])]
    public function preview(
        string $token,
        Request $request,
        SalesChannelContext $salesChannelContext,
    ): Response {
        // Strict at consumption: the store returns null only for a token with no entry, and refuses a stored
        // entry that is not a preview envelope with its own 500 rather than handing back an emptied one. The
        // 500 is deliberate — only a validated envelope is ever written, so a malformed hit is server-side
        // state and not something this caller sent.
        $payload = $this->payloadStore->load($token);

        if ($payload === null) {
            throw $this->createNotFoundException('Preview token not found or expired.');
        }

        $previewState = $this->previewPageBuilder->build($payload, $salesChannelContext->getContext());
        $resolvedSalesChannelContext = $previewState['salesChannelContext'];
        $themeId = $this->resolveThemeId($resolvedSalesChannelContext->getSalesChannelId());

        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, $resolvedSalesChannelContext->getSalesChannelId());
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $resolvedSalesChannelContext->getContext());
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $resolvedSalesChannelContext);

        if ($themeId !== null) {
            $request->attributes->set(SalesChannelRequest::ATTRIBUTE_THEME_ID, $themeId);
        }

        $response = $this->renderStorefront('@Storefront/storefront/page/content/preview.html.twig', [
            'contentPage' => ContentPage::fromRenderResult($previewState['result']),
            'headerParameters' => [],
        ]);

        $frameAncestor = $this->resolveFrameAncestor($request);
        $response->headers->set('Content-Security-Policy', \sprintf('frame-ancestors \'self\' %s;', $frameAncestor));
        // CoreSubscriber defaults to "deny" if this header is missing.
        // We set a non-enforcing value and control embedding via frame-ancestors CSP above.
        $response->headers->set(PlatformRequest::HEADER_FRAME_OPTIONS, 'ALLOWALL');

        return $response;
    }

    private function resolveThemeId(string $salesChannelId): ?string
    {
        $themeId = $this->connection->fetchOne(
            'SELECT LOWER(HEX(`theme_id`)) FROM `theme_sales_channel` WHERE `sales_channel_id` = :salesChannelId ORDER BY `theme_id` LIMIT 1',
            ['salesChannelId' => Uuid::fromHexToBytes($salesChannelId)]
        );

        if (!\is_string($themeId) || !Uuid::isValid($themeId)) {
            return null;
        }

        return $themeId;
    }

    private function resolveFrameAncestor(Request $request): string
    {
        $referer = $request->headers->get('referer');
        if (!\is_string($referer) || $referer === '') {
            return $request->getSchemeAndHttpHost();
        }

        $scheme = parse_url($referer, \PHP_URL_SCHEME);
        $host = parse_url($referer, \PHP_URL_HOST);
        $port = parse_url($referer, \PHP_URL_PORT);

        if (!\is_string($scheme) || !\is_string($host) || $scheme === '' || $host === '') {
            return $request->getSchemeAndHttpHost();
        }

        if (\is_int($port)) {
            return \sprintf('%s://%s:%d', $scheme, $host, $port);
        }

        return \sprintf('%s://%s', $scheme, $host);
    }
}
