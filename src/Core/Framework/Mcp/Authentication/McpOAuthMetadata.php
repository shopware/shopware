<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Authentication;

use Mcp\Exception\InvalidArgumentException;
use Mcp\Server\Transport\Http\OAuth\ProtectedResourceMetadata;
use Shopware\Core\Framework\Api\OAuth\OAuthResource;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\McpAllowedHostsProvider;
use Shopware\Core\Framework\Mcp\McpException;
use Symfony\Component\HttpFoundation\Request;

/**
 * URLs and discovery documents of the Admin API as OAuth authorization server (RFC 8414)
 * and of `/api/_mcp` as protected resource (RFC 9728).
 *
 * The base URL keeps the installation base path (`https://shop.example/shop`). It is taken from the
 * request when {@see McpAllowedHostsProvider} allows its host, otherwise from APP_URL.
 * The well-known URLs insert the well-known segment between host and path, so for an installation
 * in a subdirectory they sit outside Shopware and need a web server redirect.
 *
 * MCP clients may add a query to the endpoint URL, for example `?toolsets=`. RFC 9728 §3.1 keeps that query in
 * the well-known URL, and §3.3 makes the client reject a document whose `resource` differs from the URL it used.
 * The metadata URL in the challenge and the `resource` of the document therefore carry the query of the request
 * unchanged. Matching a requested resource ({@see self::matchResource()}) and the token audience still ignore it.
 *
 * The protected resource metadata is the `mcp/sdk` {@see ProtectedResourceMetadata}; the SDK has no class for the
 * authorization server metadata, so that document is built here.
 *
 * @internal
 *
 * @experimental stableVersion:v6.8.0
 */
#[Package('framework')]
class McpOAuthMetadata
{
    public const PROTECTED_RESOURCE_PREFIX = '/.well-known/oauth-protected-resource';
    public const AUTHORIZATION_SERVER_PREFIX = '/.well-known/oauth-authorization-server';

    public const ISSUER_PATH = '/api';
    public const RESOURCE_PATH = '/api/_mcp';

    public function __construct(
        private readonly McpAllowedHostsProvider $allowedHostsProvider,
        private readonly string $appUrl,
    ) {
    }

    public function getIssuer(Request $request): string
    {
        return $this->getBaseUrl($request) . self::ISSUER_PATH;
    }

    public function getResource(Request $request): string
    {
        return $this->getBaseUrl($request) . self::RESOURCE_PATH;
    }

    /**
     * For a request to the MCP endpoint: the metadata URL with the request's query, as RFC 9728 §3.1 builds it.
     */
    public function getProtectedResourceMetadataUrl(Request $request): string
    {
        return $this->insertWellKnown($this->getResource($request), self::PROTECTED_RESOURCE_PREFIX) . $this->getRawQuery($request);
    }

    public function getAuthorizationServerMetadataUrl(Request $request): string
    {
        return $this->insertWellKnown($this->getIssuer($request), self::AUTHORIZATION_SERVER_PREFIX);
    }

    /**
     * Returns the canonical resource when the requested one is this shop's MCP resource, otherwise null.
     */
    public function matchResource(Request $request, string $requested): ?string
    {
        $resource = OAuthResource::normalize($this->getResource($request));

        return $resource !== null && OAuthResource::normalize($requested) === $resource ? $resource : null;
    }

    /**
     * For a request to the metadata URL: the `resource` keeps the request's query, so it is identical to the
     * MCP endpoint URL the client derived the metadata URL from (RFC 9728 §3.3).
     *
     * Since `mcp/sdk` 0.9 the SDK class rejects plain http outside loopback hosts, as the MCP authorization
     * specification requires https; such a shop gets no document.
     *
     * @return array<string, mixed>
     */
    public function buildProtectedResourceMetadata(Request $request): array
    {
        $resource = $this->getResource($request) . $this->getRawQuery($request);

        try {
            $metadata = new ProtectedResourceMetadata(
                resource: $resource,
                authorizationServers: [$this->getIssuer($request)],
            );
        } catch (InvalidArgumentException $exception) {
            throw McpException::oauthProtectedResourceMetadataUnavailable($resource, $exception);
        }

        // `mcp/sdk` 0.9 always adds `bearer_methods_supported`, 0.8 only takes it as extra field under another constructor.
        return $metadata->jsonSerialize() + ['bearer_methods_supported' => ['header']];
    }

    /**
     * @return array<string, string|bool|list<string>>
     */
    public function buildAuthorizationServerMetadata(Request $request): array
    {
        $issuer = $this->getIssuer($request);

        return [
            'issuer' => $issuer,
            'authorization_endpoint' => $issuer . '/oauth/authorize',
            'token_endpoint' => $issuer . '/oauth/token',
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256'],
            'token_endpoint_auth_methods_supported' => ['none'],
            'authorization_response_iss_parameter_supported' => true,
        ];
    }

    private function getBaseUrl(Request $request): string
    {
        $host = strtolower($request->getHost());

        if ($host !== '' && \in_array($host, $this->allowedHostsProvider->getAllowedHosts(), true)) {
            return $request->getSchemeAndHttpHost() . rtrim($request->getBasePath(), '/');
        }

        return rtrim($this->appUrl, '/');
    }

    /**
     * The query exactly as sent, because {@see Request::getQueryString()} sorts and re-encodes it.
     */
    private function getRawQuery(Request $request): string
    {
        $query = $request->server->get('QUERY_STRING');

        return \is_string($query) && $query !== '' ? '?' . $query : '';
    }

    private function insertWellKnown(string $url, string $wellKnownPrefix): string
    {
        $path = (string) parse_url($url, \PHP_URL_PATH);
        $origin = substr($url, 0, \strlen($url) - \strlen($path));

        return $origin . $wellKnownPrefix . $path;
    }
}
