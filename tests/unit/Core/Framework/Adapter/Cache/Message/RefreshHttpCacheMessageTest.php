<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache\Message;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\Message\RefreshHttpCacheMessage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RefreshHttpCacheMessage::class)]
class RefreshHttpCacheMessageTest extends TestCase
{
    /**
     * Environment variables written by system:setup and the credential-bearing DSNs and URLs Shopware reads.
     * The message is persisted by the message queue transport, so none of them may end up in it.
     */
    private const SHOPWARE_ENVIRONMENT_VARIABLES = [
        'ADMIN_OPENSEARCH_URL',
        'APP_ENV',
        'APP_SECRET',
        'APP_URL',
        'BLUE_GREEN_DEPLOYMENT',
        'COMPOSER_HOME',
        'DATABASE_REPLICA_0_URL',
        'DATABASE_SSL_CA',
        'DATABASE_SSL_CERT',
        'DATABASE_SSL_DONT_VERIFY_SERVER_CERT',
        'DATABASE_SSL_KEY',
        'DATABASE_URL',
        'INSTANCE_ID',
        'LOCK_DSN',
        'MAILER_DSN',
        'MESSENGER_TRANSPORT_DSN',
        'MESSENGER_TRANSPORT_FAILURE_DSN',
        'MESSENGER_TRANSPORT_LOW_PRIORITY_DSN',
        'OPENSEARCH_URL',
        'PRODUCT_ANALYTICS_GATEWAY_URL',
        'SERVICE_REGISTRY_URL',
        'SHOPWARE_ADMIN_ES_ENABLED',
        'SHOPWARE_ADMIN_ES_INDEX_PREFIX',
        'SHOPWARE_ADMIN_ES_REFRESH_INDICES',
        'SHOPWARE_CACHE_ID',
        'SHOPWARE_CDN_STRATEGY_DEFAULT',
        'SHOPWARE_ES_ENABLED',
        'SHOPWARE_ES_INDEX_PREFIX',
        'SHOPWARE_ES_INDEXING_ENABLED',
        'SHOPWARE_HTTP_CACHE_ENABLED',
        'SHOPWARE_HTTP_DEFAULT_TTL',
    ];

    public function testFromRequestKeepsOnlyWhatIsNeededToRebuildTheRequest(): void
    {
        $leakedValue = 'leaked-environment-value';
        $keptServer = [
            'HTTP_HOST' => 'shop.example',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTPS' => 'on',
            'REQUEST_URI' => '/detail/1',
            'REQUEST_METHOD' => 'GET',
            'SCRIPT_NAME' => '/index.php',
            'REMOTE_ADDR' => '10.0.0.1',
        ];
        $droppedServer = [
            ...array_fill_keys(self::SHOPWARE_ENVIRONMENT_VARIABLES, $leakedValue),
            // environment variables that look like headers, from the process environment and from a .env file
            'HTTP_PROXY' => $leakedValue,
            'HTTP_DOTENV_PROXY' => $leakedValue,
            'SYMFONY_DOTENV_VARS' => 'APP_SECRET,HTTP_DOTENV_PROXY',
            // credentials sent with the request, e.g. basic auth in front of a staging shop, and the session id
            'HTTP_AUTHORIZATION' => 'Basic ' . $leakedValue,
            'HTTP_PROXY_AUTHORIZATION' => 'Basic ' . $leakedValue,
            'HTTP_COOKIE' => PlatformRequest::FALLBACK_SESSION_NAME . '=' . $leakedValue . '; sw-cache-hash=context-hash',
        ];
        $request = new Request(
            query: ['page' => '2'],
            attributes: ['_route' => 'frontend.detail.page'],
            cookies: [
                PlatformRequest::FALLBACK_SESSION_NAME => $leakedValue,
                'sw-cache-hash' => 'context-hash',
            ],
            server: [...$keptServer, ...$droppedServer],
        );

        $message = RefreshHttpCacheMessage::fromRequest('lock-key', $request, PlatformRequest::FALLBACK_SESSION_NAME);

        static::assertSame('lock-key', $message->lockKey);
        static::assertSame(['page' => '2'], $message->query);
        static::assertSame(['_route' => 'frontend.detail.page'], $message->attributes);
        static::assertSame(['sw-cache-hash' => 'context-hash'], $message->cookies);
        static::assertSame($keptServer, $message->server);
        static::assertSame(Request::getTrustedProxies(), $message->trustedIps);
        static::assertSame(Request::getTrustedHeaderSet(), $message->trustedHeaderSet);
        static::assertStringNotContainsString($leakedValue, serialize($message));
    }
}
