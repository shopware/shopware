<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OAuth;

use Shopware\Core\Framework\Log\Package;

/**
 * RFC 8707 resource indicators of the Admin API authorization server.
 *
 * A token issued with a resource is bound to it: the JWT lists the resource as a further `aud` entry
 * next to the client id, and the bearer token validator exposes it as the request attribute
 * {@see self::REQUEST_ATTRIBUTE}. The MCP endpoint is currently the only resource a token can be bound to.
 *
 * @internal
 */
#[Package('framework')]
final class OAuthResource
{
    public const PARAMETER = 'resource';

    public const REQUEST_ATTRIBUTE = 'sw-oauth-resource';

    /**
     * Set by the resource endpoint on the subrequests it starts itself (MCP app script tools), carrying the
     * resource of its own token. Only a subrequest with this attribute accepts a token bound to a resource.
     * A request attribute cannot be set from outside, unlike the copied `Authorization` header of an ESI subrequest.
     */
    public const SUBREQUEST_ATTRIBUTE = 'sw-oauth-subrequest-resource';

    public const ERROR_INVALID_TARGET = 'invalid_target';

    // outside the range of the error codes league/oauth2-server assigns
    public const ERROR_CODE_INVALID_TARGET = 101;

    private const DEFAULT_PORTS = ['http' => 80, 'https' => 443];

    /**
     * Returns the canonical form used to compare and store resource indicators,
     * or null when the value is no absolute http(s) URI without fragment.
     * Query parameters are ignored; MCP clients may append connection options such as `?toolsets=`.
     */
    public static function normalize(string $resource): ?string
    {
        if (str_contains($resource, '#')) {
            return null;
        }

        $parts = parse_url($resource);
        if ($parts === false) {
            return null;
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower($parts['host'] ?? '');
        if (!\array_key_exists($scheme, self::DEFAULT_PORTS) || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $port = $parts['port'] ?? null;
        $authority = $port === null || $port === self::DEFAULT_PORTS[$scheme] ? $host : $host . ':' . $port;

        return $scheme . '://' . $authority . rtrim($parts['path'] ?? '', '/');
    }
}
