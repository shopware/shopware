<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Discovery;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\UcpException;
use Shopware\Core\Framework\Ucp\UcpVersion;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Serves the UCP business profile under /.well-known/ucp and version-pinned
 * leaf documents under /.well-known/ucp/{version}.
 *
 * Per the spec: profiles MUST be served over HTTPS, MUST NOT redirect, and
 * the Cache-Control header MUST be `public` with `max-age >= 60s`.
 *
 * @internal
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => ['storefront']])]
class WellKnownUcpController
{
    public function __construct(
        private readonly UcpProfileBuilder $profileBuilder,
        private readonly UcpConfigProvider $configProvider,
        private readonly SalesChannelDomainResolver $domainResolver,
    ) {
    }

    #[Route(
        path: '/.well-known/ucp',
        name: 'ucp.well_known',
        defaults: ['auth_required' => false, 'XmlHttpRequest' => true],
        methods: ['GET', 'OPTIONS']
    )]
    public function profile(Request $request): Response
    {
        if (!Feature::isActive('UCP_SERVER')) {
            return new Response(null, Response::HTTP_NOT_FOUND);
        }

        $context = Context::createDefaultContext();
        $domain = $this->domainResolver->resolve($request, $context);

        if ($domain === null) {
            return new Response(null, Response::HTTP_NOT_FOUND);
        }

        $config = $this->configProvider->forSalesChannel($domain->getSalesChannelId(), $context);
        if ($config === null || !$config->isActive()) {
            return new Response(null, Response::HTTP_NOT_FOUND);
        }

        $profile = $this->profileBuilder->build($config, $domain, $context);

        return $this->respond($profile);
    }

    #[Route(
        path: '/.well-known/ucp/{version}',
        name: 'ucp.well_known.versioned',
        requirements: ['version' => '\d{4}-\d{2}-\d{2}'],
        defaults: ['auth_required' => false, 'XmlHttpRequest' => true],
        methods: ['GET']
    )]
    public function versionedProfile(Request $request, string $version): Response
    {
        if (!Feature::isActive('UCP_SERVER')) {
            return new Response(null, Response::HTTP_NOT_FOUND);
        }

        if (!UcpVersion::isValidFormat($version)) {
            throw UcpException::versionInvalid($version);
        }

        if (!\in_array($version, UcpVersion::HISTORICAL, true)) {
            return new Response(null, Response::HTTP_NOT_FOUND);
        }

        $context = Context::createDefaultContext();
        $domain = $this->domainResolver->resolve($request, $context);
        if ($domain === null) {
            return new Response(null, Response::HTTP_NOT_FOUND);
        }

        $config = $this->configProvider->forSalesChannel($domain->getSalesChannelId(), $context);
        if ($config === null || !$config->isActive()) {
            return new Response(null, Response::HTTP_NOT_FOUND);
        }

        // Pin the requested historical version
        $clone = clone $config;
        $clone->setUcpVersion($version);

        $profile = $this->profileBuilder->build($clone, $domain, $context);
        // Version-pinned profiles are leaves — no supported_versions
        unset($profile['ucp']['supported_versions']);

        return $this->respond($profile);
    }

    /**
     * @param array<string, mixed> $profile
     */
    private function respond(array $profile): JsonResponse
    {
        $response = new JsonResponse($profile);
        $response->headers->set('Content-Type', 'application/json; charset=utf-8');
        $response->headers->set('Cache-Control', 'public, max-age=300, stale-while-revalidate=60');
        // Profile fetches MUST NOT redirect — assert this via Vary on Host
        $response->headers->set('Vary', 'Host');

        return $response;
    }
}
