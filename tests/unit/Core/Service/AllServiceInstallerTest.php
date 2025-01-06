<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Service\AllServiceInstaller;
use Shopware\Core\Service\ServiceLifecycle;
use Shopware\Core\Service\ServiceRegistryClient;
use Shopware\Core\Service\ServiceRegistryEntry;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[CoversClass(AllServiceInstaller::class)]
class AllServiceInstallerTest extends TestCase
{
    public function testAllServicesAreInstalledIfNoneExist(): void
    {
        $serviceRegistryClient = $this->createMock(ServiceRegistryClient::class);
        $serviceLifeCycle = $this->createMock(ServiceLifecycle::class);

        $serviceInstaller = new AllServiceInstaller(
            '1',
            'prod',
            $serviceRegistryClient,
            $serviceLifeCycle,
            $this->buildAppRepository(),
        );

        $serviceRegistryClient->expects(static::once())
            ->method('getAll')
            ->willReturn([
                new ServiceRegistryEntry('Service1', 'https://service-1.com', 'Service 1', ''),
                new ServiceRegistryEntry('Service2', 'https://service-2.com', 'Service 2', ''),
            ]);

        $matcher = static::exactly(2);
        $serviceLifeCycle->expects($matcher)
            ->method('install')
            ->willReturnCallback(function (ServiceRegistryEntry $serviceRegistryEntry) use ($matcher): bool {
                match ($matcher->numberOfInvocations()) {
                    1 => $this->assertEquals('Service1', $serviceRegistryEntry->name),
                    2 => $this->assertEquals('Service2', $serviceRegistryEntry->name),
                    default => throw new \UnhandledMatchError(),
                };

                return true;
            });

        $serviceInstaller->install(Context::createDefaultContext());
    }

    #[DataProvider('serviceTogglesProvider')]
    public function testServicesCanBeEnabledOrDisabled(string $enabled, string $appEnv, bool $shouldBeActive): void
    {
        $serviceRegistryClient = $this->createMock(ServiceRegistryClient::class);
        $serviceLifeCycle = $this->createMock(ServiceLifecycle::class);

        $serviceInstaller = new AllServiceInstaller(
            $enabled,
            $appEnv,
            $serviceRegistryClient,
            $serviceLifeCycle,
            $this->buildAppRepository(),
        );

        if ($shouldBeActive) {
            $serviceRegistryClient->expects(static::once())
            ->method('getAll')
            ->willReturn([
                new ServiceRegistryEntry('Service1', 'https://service-1.com', 'Service 1', ''),
            ]);

            $serviceLifeCycle->expects(static::once())
                ->method('install')
                ->willReturnCallback(function (ServiceRegistryEntry $serviceRegistryEntry): bool {
                    $this->assertEquals('Service1', $serviceRegistryEntry->name);

                    return true;
                });
        } else {
            $serviceRegistryClient->expects(static::never())
                ->method('getAll');

            $serviceLifeCycle->expects(static::never())
                ->method('install');
        }

        $serviceInstaller->install(Context::createDefaultContext());
    }

    public static function serviceTogglesProvider(): \Generator
    {
        yield 'not explicitly configured on prod should default to enabled' => ['auto', 'prod', true];

        yield 'not explicitly configured on non-prod should default to disabled' => ['auto', 'dev', false];

        yield 'explicitly enabled on dev should override default and be enabled' => ['1', 'dev', true];

        yield '"true" is treated as truthy' => ['true', 'dev', true];

        yield '"on" is treated as truthy' => ['on', 'dev', true];

        yield 'explicitly disabled on prod should override default and be disabled' => ['0', 'prod', false];

        yield '"false" is treated as falsy' => ['false', 'prod', false];

        yield '"off" is treated as falsy' => ['off', 'prod', false];
    }

    public function testOnlyNewServicesAreInstalled(): void
    {
        $app1 = new AppEntity();
        $app1->setUniqueIdentifier(Uuid::randomHex());
        $app1->setName('Service1');

        $serviceRegistryClient = $this->createMock(ServiceRegistryClient::class);
        $serviceLifeCycle = $this->createMock(ServiceLifecycle::class);

        $serviceInstaller = new AllServiceInstaller(
            '1',
            'prod',
            $serviceRegistryClient,
            $serviceLifeCycle,
            $this->buildAppRepository([$app1]),
        );

        $serviceRegistryClient->expects(static::once())
            ->method('getAll')
            ->willReturn([
                new ServiceRegistryEntry('Service1', 'Service 1', 'https://service-1.com', '/app-endpoint'),
                new ServiceRegistryEntry('Service2', 'Service 2', 'https://service-2.com', '/app-endpoint'),
            ]);

        $serviceLifeCycle->expects(static::exactly(1))
            ->method('install')
            ->willReturnCallback(function (ServiceRegistryEntry $serviceRegistryEntry): bool {
                $this->assertEquals('Service2', $serviceRegistryEntry->name);

                return true;
            });

        $serviceInstaller->install(Context::createDefaultContext());
    }

    public function testNoServicesAreInstalledIfAllExist(): void
    {
        $app1 = new AppEntity();
        $app1->setUniqueIdentifier(Uuid::randomHex());
        $app1->setName('Service1');
        $app2 = new AppEntity();
        $app2->setUniqueIdentifier(Uuid::randomHex());
        $app2->setName('Service2');

        $serviceRegistryClient = $this->createMock(ServiceRegistryClient::class);
        $serviceLifeCycle = $this->createMock(ServiceLifecycle::class);

        $serviceInstaller = new AllServiceInstaller(
            '1',
            'prod',
            $serviceRegistryClient,
            $serviceLifeCycle,
            $this->buildAppRepository([$app1, $app2]),
        );

        $serviceRegistryClient->expects(static::once())
            ->method('getAll')
            ->willReturn([
                new ServiceRegistryEntry('Service1', 'Service 1', 'https://service-1.com', '/app-endpoint'),
                new ServiceRegistryEntry('Service2', 'Service 2', 'https://service-2.com', '/app-endpoint'),
            ]);

        $serviceLifeCycle->expects(static::never())
            ->method('install');

        $serviceInstaller->install(Context::createDefaultContext());
    }

    /**
     * @param array<AppEntity> $apps
     *
     * @return StaticEntityRepository<AppCollection>
     */
    private function buildAppRepository(array $apps = []): StaticEntityRepository
    {
        /** @var StaticEntityRepository<AppCollection> $appRepository */
        $appRepository = new StaticEntityRepository([
            new AppCollection($apps),
        ]);

        return $appRepository;
    }
}
