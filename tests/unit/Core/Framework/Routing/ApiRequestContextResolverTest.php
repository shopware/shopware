<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Routing;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\ApiRequestContextResolver;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\Framework\Routing\RouteScopeRegistry;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(ApiRequestContextResolver::class)]
class ApiRequestContextResolverTest extends TestCase
{
    public function testEmptyLanguageAndCurrencyHeadersFallBackToDefaults(): void
    {
        $request = $this->createApiRequest();
        $request->headers->set(PlatformRequest::HEADER_LANGUAGE_ID, '');
        $request->headers->set(PlatformRequest::HEADER_CURRENCY_ID, '');

        $this->createResolver($this->createConnection())->resolve($request);

        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);
        static::assertInstanceOf(Context::class, $context);
        static::assertInstanceOf(SystemSource::class, $context->getSource());
        static::assertSame(Defaults::LANGUAGE_SYSTEM, $context->getLanguageId());
        static::assertSame(Defaults::CURRENCY, $context->getCurrencyId());
    }

    public function testEmptyAppIntegrationIdHeaderIsIgnored(): void
    {
        $userId = Uuid::randomHex();
        $connection = $this->createConnection();
        $connection->insert('user', [
            'id' => Uuid::fromHexToBytes($userId),
            'admin' => 1,
        ]);

        $request = $this->createApiRequest();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_USER_ID, $userId);
        $request->headers->set(PlatformRequest::HEADER_APP_INTEGRATION_ID, '');

        $this->createResolver($connection)->resolve($request);

        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);
        static::assertInstanceOf(Context::class, $context);
        $source = $context->getSource();
        static::assertInstanceOf(AdminApiSource::class, $source);
        static::assertSame($userId, $source->getUserId());
        static::assertNull($source->getIntegrationId());
    }

    public function testEmptyAppUserIdHeaderIsIgnoredForIntegrationAuth(): void
    {
        $integrationId = Uuid::randomHex();
        $accessKey = AccessKeyHelper::generateAccessKey('integration');
        $connection = $this->createConnection();
        $connection->insert('integration', [
            'id' => Uuid::fromHexToBytes($integrationId),
            'access_key' => $accessKey,
            'admin' => 0,
        ]);

        $request = $this->createApiRequest();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_ACCESS_TOKEN_ID, 'test');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID, $accessKey);
        $request->headers->set(PlatformRequest::HEADER_APP_USER_ID, '');

        $this->createResolver($connection)->resolve($request);

        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);
        static::assertInstanceOf(Context::class, $context);
        $source = $context->getSource();
        static::assertInstanceOf(AdminApiSource::class, $source);
        static::assertNull($source->getUserId());
        static::assertSame($integrationId, $source->getIntegrationId());
    }

    private function createResolver(Connection $connection): ApiRequestContextResolver
    {
        return new ApiRequestContextResolver(
            $connection,
            new RouteScopeRegistry([new ApiRouteScope()])
        );
    }

    private function createApiRequest(): Request
    {
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, [ApiRouteScope::ID]);

        return $request;
    }

    private function createConnection(): Connection
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement('CREATE TABLE currency (id BLOB PRIMARY KEY, item_rounding TEXT NOT NULL)');
        $connection->executeStatement('CREATE TABLE user (id BLOB PRIMARY KEY, admin INTEGER NOT NULL)');
        $connection->executeStatement('CREATE TABLE acl_role (id BLOB PRIMARY KEY, privileges TEXT NOT NULL)');
        $connection->executeStatement('CREATE TABLE acl_user_role (user_id BLOB NOT NULL, acl_role_id BLOB NOT NULL)');
        $connection->executeStatement('CREATE TABLE integration (id BLOB PRIMARY KEY, access_key TEXT NOT NULL, admin INTEGER NOT NULL)');
        $connection->executeStatement('CREATE TABLE integration_role (integration_id BLOB NOT NULL, acl_role_id BLOB NOT NULL)');
        $connection->executeStatement('CREATE TABLE app (id BLOB PRIMARY KEY, integration_id BLOB NOT NULL, acl_role_id BLOB NOT NULL)');
        $connection->insert('currency', [
            'id' => Uuid::fromHexToBytes(Defaults::CURRENCY),
            'item_rounding' => json_encode([
                'decimals' => 2,
                'interval' => 0.01,
                'roundForNet' => true,
            ], \JSON_THROW_ON_ERROR),
        ]);

        return $connection;
    }
}
