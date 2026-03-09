<?php declare(strict_types=1);

namespace Shopware\Storefront\ContentSystem\Rendering;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Package('framework')]
class ContentSystemDemoPageLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly StorefrontContentRenderer $contentRenderer
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function load(string $landingPageId, Request $request, SalesChannelContext $context): array
    {
        $fullResponse = $this->requestStoreApiPayload('/store-api/content/landing-page/' . $landingPageId, $request, $context);

        return [
            'landingPageId' => $landingPageId,
            'salesChannelId' => $context->getSalesChannelId(),
            'fullEndpoint' => $fullResponse['endpoint'],
            'renderedLayoutHtml' => $this->contentRenderer->renderLayout($fullResponse['payload']),
            'fullError' => $fullResponse['error'],
        ];
    }

    /**
     * @return array{endpoint: string, payload: array<string, mixed>|null, error: string|null}
     */
    public function loadSkeleton(string $landingPageId, Request $request, SalesChannelContext $context): array
    {
        return $this->requestStoreApiPayload('/store-api/content-skeleton/landing-page/' . $landingPageId, $request, $context);
    }

    /**
     * @return array{endpoint: string, payload: array<string, mixed>|null, error: string|null}
     */
    private function requestStoreApiPayload(string $path, Request $request, SalesChannelContext $context): array
    {
        $endpoint = $request->getSchemeAndHttpHost() . $path;
        $accessKey = $context->getSalesChannel()->getAccessKey();
        $payload = null;
        $error = null;

        try {
            $response = $this->httpClient->request('GET', $endpoint, [
                'headers' => ['sw-access-key' => $accessKey],
            ]);

            $payload = $response->toArray(false);
        } catch (ExceptionInterface $e) {
            $error = $e->getMessage();
        }

        return [
            'endpoint' => $endpoint,
            'payload' => $payload,
            'error' => $error,
        ];
    }
}
