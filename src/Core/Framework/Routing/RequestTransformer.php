<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\SalesChannelDomain\AbstractDomainLoader;
use Symfony\Component\HttpFoundation\Request;

/**
 * @phpstan-import-type Domain from AbstractDomainLoader
 */
#[Package('framework')]
class RequestTransformer implements RequestTransformerInterface
{
    public function __construct(
        private readonly AbstractDomainLoader $domainLoader,
    ) {
    }

    public function transform(Request $request): Request
    {
        if (!$this->isStoreApiRequest($request->getPathInfo())) {
            return $request;
        }

        $domain = $this->domainLoader->findDomain($request);

        if ($domain === null) {
            return $request;
        }

        $transformedRequest = $request->duplicate();

        $transformedRequest->attributes->set(SalesChannelRequest::ATTRIBUTE_IS_STORE_API_REQUEST, true);
        $transformedRequest->attributes->set(SalesChannelRequest::ATTRIBUTE_DOMAIN_LOCALE, $domain['locale']);
        $transformedRequest->attributes->set(SalesChannelRequest::ATTRIBUTE_DOMAIN_SNIPPET_SET_ID, $domain['snippetSetId']);
        $transformedRequest->attributes->set(SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID, $domain['currencyId']);
        $transformedRequest->attributes->set(SalesChannelRequest::ATTRIBUTE_DOMAIN_ID, $domain['id']);

        $transformedRequest->attributes->set(
            SalesChannelRequest::ATTRIBUTE_SALES_CHANNEL_MAINTENANCE,
            (bool) $domain['maintenance']
        );

        $transformedRequest->attributes->set(
            SalesChannelRequest::ATTRIBUTE_SALES_CHANNEL_MAINTENANCE_IP_WHITELIST,
            $domain['maintenanceIpWhitelist']
        );

        if (!$request->headers->has(PlatformRequest::HEADER_LANGUAGE_ID)) {
            $transformedRequest->headers->set(PlatformRequest::HEADER_LANGUAGE_ID, $domain['languageId']);
        }

        return $transformedRequest;
    }

    /**
     * @return array<string, mixed>
     */
    public function extractInheritableAttributes(Request $sourceRequest): array
    {
        return [];
    }

    private function isStoreApiRequest(string $pathInfo): bool
    {
        $pathInfo = '/' . trim($pathInfo, '/') . '/';

        if (str_contains($pathInfo, '/' . StoreApiRouteScope::ID . '/')) {
            return true;
        }

        return false;
    }
}
