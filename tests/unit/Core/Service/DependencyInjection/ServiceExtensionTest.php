<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\DependencyInjection\ServiceExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\EnvPlaceholderParameterBag;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ServiceExtension::class)]
class ServiceExtensionTest extends TestCase
{
    #[DataProvider('nonProductionEnvironmentProvider')]
    public function testRegistryUrlIsConfigurableOutsideOfProduction(string $environment): void
    {
        $container = new ContainerBuilder(new EnvPlaceholderParameterBag(['kernel.environment' => $environment]));

        (new ServiceExtension())->prepend($container);

        static::assertSame('%env(SERVICE_REGISTRY_URL)%', $container->getParameter('shopware.service_registry.url'));
    }

    public function testRegistryUrlIgnoresTheEnvironmentVariableInProduction(): void
    {
        $container = new ContainerBuilder(new EnvPlaceholderParameterBag(['kernel.environment' => 'prod']));

        (new ServiceExtension())->prepend($container);

        static::assertSame(ServiceExtension::DEFAULT_REGISTRY_URL, $container->getParameter('shopware.service_registry.url'));
    }

    public function testRegistryHttpClientUsesTheResolvedRegistryUrl(): void
    {
        $container = new ContainerBuilder(new EnvPlaceholderParameterBag(['kernel.environment' => 'prod']));

        (new ServiceExtension())->prepend($container);

        static::assertSame([[
            'http_client' => [
                'scoped_clients' => [
                    'service_registry.http_client' => [
                        'base_uri' => '%shopware.service_registry.url%',
                        'max_duration' => 5,
                    ],
                ],
            ],
        ]], $container->getExtensionConfig('framework'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function nonProductionEnvironmentProvider(): iterable
    {
        yield 'development environment' => ['dev'];
        yield 'test environment' => ['test'];
    }
}
