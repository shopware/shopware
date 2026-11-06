<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Mcp\DualEra;

use Mcp\Capability\RegistryInterface;
use Mcp\Server;
use Mcp\Server\Builder;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\AllowList\McpAllowlistFilter;
use Shopware\Core\Framework\Mcp\AllowList\McpAllowlistProvider;
use Shopware\Core\Framework\Mcp\Controller\McpServerController;
use Shopware\Core\Framework\Mcp\Controller\StoreApiMcpServerController;
use Shopware\Core\Framework\Mcp\Http\McpHttpTransportFactory;
use Shopware\Core\Framework\Mcp\Notification\McpListChangedNotifier;
use Shopware\Core\Framework\Mcp\RateLimit\McpRateLimiter;
use Shopware\Core\Framework\Mcp\Session\McpSessionIdValidator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Builds a server with both protocol legs from the builder the container configured, and puts it into
 * both MCP controllers. The container must not have created the controllers yet. The builder is put back
 * to the handshake-only pin right away, so nothing else built from it changes.
 *
 * @internal
 */
#[Package('framework')]
final class DualEraServerFixture
{
    public static function install(ContainerInterface $container): void
    {
        $container->set(McpServerController::class, new McpServerController(
            self::dualEraServer($container, 'mcp.server.admin.builder'),
            self::service($container, McpHttpTransportFactory::class),
            self::service($container, McpRateLimiter::class),
            self::service($container, McpSessionIdValidator::class),
            self::service($container, McpAllowlistProvider::class),
            self::service($container, LoggerInterface::class),
            self::service($container, McpAllowlistFilter::class),
            self::service($container, McpListChangedNotifier::class),
        ));

        $container->set(StoreApiMcpServerController::class, new StoreApiMcpServerController(
            self::dualEraServer($container, 'mcp.server.store_api.builder'),
            self::service($container, McpHttpTransportFactory::class),
            self::service($container, McpRateLimiter::class),
            self::service($container, McpSessionIdValidator::class),
            self::service($container, LoggerInterface::class),
            self::service($container, McpListChangedNotifier::class, 'mcp.store_api.list_changed_notifier'),
        ));

        OversizedResultTool::register(self::service($container, RegistryInterface::class, 'mcp.server.store_api.registry'));
    }

    private static function dualEraServer(ContainerInterface $container, string $builderId): Server
    {
        $builder = self::service($container, Builder::class, $builderId);

        try {
            return $builder->setModernVersions([McpEra::Stateless->protocolVersion()])->build();
        } finally {
            $builder->withoutModernEra();
        }
    }

    /**
     * The container is the test container, which also holds the private services.
     *
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private static function service(ContainerInterface $container, string $class, ?string $id = null): object
    {
        $service = $container->get($id ?? $class);
        \assert($service instanceof $class);

        return $service;
    }
}
