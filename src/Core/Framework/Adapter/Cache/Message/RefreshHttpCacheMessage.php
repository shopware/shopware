<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\Message;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
final class RefreshHttpCacheMessage implements AsyncMessageInterface
{
    /**
     * Headers that are never needed to rebuild a cacheable page.
     */
    private const DROPPED_HEADERS = [
        'HTTP_AUTHORIZATION', // e.g. basic auth in front of a staging shop
        'HTTP_PROXY_AUTHORIZATION',
        'HTTP_COOKIE', // cookies are passed separately
        'HTTP_PROXY', // usually an environment variable with proxy credentials
    ];

    /**
     * @param array<mixed> $query
     * @param array<mixed> $attributes
     * @param array<mixed> $cookies
     * @param array<mixed> $server
     * @param array<mixed> $trustedIps
     */
    public function __construct(public string $lockKey, public array $query = [], public array $attributes = [], public array $cookies = [], public array $server = [], public array $trustedIps = [], public int $trustedHeaderSet = Request::HEADER_FORWARDED)
    {
    }

    /**
     * The message is persisted by the message queue transport, so it only gets what the handler needs to rebuild the request.
     */
    public static function fromRequest(string $lockKey, Request $request, string $sessionName): self
    {
        return new self(
            lockKey: $lockKey,
            query: $request->query->all(),
            attributes: $request->attributes->all(),
            // The handler uses a mock session, so the session cookie is not needed
            cookies: array_diff_key($request->cookies->all(), [$sessionName => true]),
            server: self::getServerParameters($request),
            trustedIps: Request::getTrustedProxies(),
            trustedHeaderSet: Request::getTrustedHeaderSet(),
        );
    }

    /**
     * The server bag also contains the process environment, which can hold credentials.
     * So only the request headers and the server parameters Symfony needs to build a request are kept.
     *
     * @return array<string, mixed>
     */
    private static function getServerParameters(Request $request): array
    {
        // Only the names are used. The URL just has to be https, so that Symfony also sets HTTPS and the scheme is kept
        $symfonyNames = array_keys(Request::create('https://localhost/')->server->all());
        $droppedNames = [...self::DROPPED_HEADERS, ...self::getDotenvNames($request)];

        $parameters = [];
        foreach ($request->server->all() as $name => $value) {
            $isHeader = str_starts_with((string) $name, 'HTTP_') && !\in_array($name, $droppedNames, true);

            if ($isHeader || \in_array($name, $symfonyNames, true)) {
                $parameters[$name] = $value;
            }
        }

        return $parameters;
    }

    /**
     * Environment variables can look like headers. Symfony lists the names loaded from .env files.
     *
     * @return list<string>
     */
    private static function getDotenvNames(Request $request): array
    {
        $names = $request->server->get('SYMFONY_DOTENV_VARS');

        return \is_string($names) && $names !== '' ? explode(',', $names) : [];
    }
}
