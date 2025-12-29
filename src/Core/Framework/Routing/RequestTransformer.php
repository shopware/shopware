<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
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
        if ($request->attributes->has(SalesChannelRequest::ATTRIBUTE_IS_STORE_API_REQUEST) && $request->attributes->getBoolean(SalesChannelRequest::ATTRIBUTE_IS_STORE_API_REQUEST)) {
            return $request;
        }

        if (!$this->isStoreApiRequest($request)) {
            return $request;
        }

        $domain = $this->domainLoader->findDomain($request);

        $transformedRequest = $request->duplicate();

        $transformedRequest->attributes->set(SalesChannelRequest::ATTRIBUTE_IS_STORE_API_REQUEST, true);

        if ($domain === null) {
            return $transformedRequest;
        }

        $transformedRequest->attributes->set(SalesChannelRequest::ATTRIBUTE_DOMAIN_LOCALE, $domain['locale']);
        $transformedRequest->attributes->set(SalesChannelRequest::ATTRIBUTE_DOMAIN_SNIPPET_SET_ID, $domain['snippetSetId']);
        $transformedRequest->attributes->set(SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID, $domain['currencyId']);
        $transformedRequest->attributes->set(SalesChannelRequest::ATTRIBUTE_DOMAIN_ID, $domain['id']);

        $transformedRequest->attributes->set(
            SalesChannelRequest::ATTRIBUTE_SALES_CHANNEL_MAINTENANCE,
            (bool) $domain['maintenance']
        );

        $transformedRequest->attributes->set(
            SalesChannelRequest::ATTRIBUTE_SALES_CHANNEL_MAINTENANCE_IP_ALLOWLIST,
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

    private function isStoreApiRequest(Request $request): bool
    {
        // Check route scope first (if routing already happened)
        /** @var list<string> $scope */
        $scope = $request->attributes->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, []);
        if (\in_array(StoreApiRouteScope::ID, $scope, true)) {
            return true;
        }

        // Fall back to path-based check (before routing)
        $pathInfo = '/' . trim($request->getPathInfo(), '/') . '/';

        return str_contains($pathInfo, '/' . StoreApiRouteScope::ID . '/');
    }
}
