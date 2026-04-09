<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\DevOps;
use Shopware\Core\DevOps\Docs\App\DocsAppEventCommand;
use Shopware\Core\DevOps\Docs\Script\HooksReferenceGenerator;
use Shopware\Core\DevOps\System\Command\SyncComposerVersionCommand;
use Shopware\Core\DevOps\System\Command\SystemDumpDatabaseCommand;
use Shopware\Core\DevOps\System\Command\SystemRestoreDatabaseCommand;
use Shopware\Core\DevOps\Test\Command\MakeCoverageTestCommand;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DevOps::class)]
class DevOpsTest extends TestCase
{
    /**
     * @param list<class-string> $expectedServices
     * @param list<class-string> $unexpectedServices
     */
    #[DataProvider('buildDataProvider')]
    public function testBuildLoadsServices(string $environment, array $expectedServices, array $unexpectedServices): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', $environment);

        $bundle = new DevOps();
        $bundle->build($container);

        foreach ($expectedServices as $service) {
            static::assertTrue($container->has($service), \sprintf('Expected service "%s" to be registered', $service));
        }

        foreach ($unexpectedServices as $service) {
            static::assertFalse($container->has($service), \sprintf('Expected service "%s" NOT to be registered', $service));
        }
    }

    /**
     * @return \Generator<string, array{environment: string, expectedServices: list<class-string>, unexpectedServices: list<class-string>}>
     */
    public static function buildDataProvider(): \Generator
    {
        $baseServices = [
            SyncComposerVersionCommand::class,
            DocsAppEventCommand::class,
            HooksReferenceGenerator::class,
        ];

        $e2eOnlyServices = [
            SystemDumpDatabaseCommand::class,
            SystemRestoreDatabaseCommand::class,
        ];

        yield 'production environment' => [
            'environment' => 'prod',
            'expectedServices' => $baseServices,
            'unexpectedServices' => [...$e2eOnlyServices, MakeCoverageTestCommand::class],
        ];

        yield 'test environment' => [
            'environment' => 'test',
            'expectedServices' => $baseServices,
            'unexpectedServices' => [...$e2eOnlyServices, MakeCoverageTestCommand::class],
        ];

        yield 'staging environment' => [
            'environment' => 'staging',
            'expectedServices' => $baseServices,
            'unexpectedServices' => [...$e2eOnlyServices, MakeCoverageTestCommand::class],
        ];

        yield 'e2e environment' => [
            'environment' => 'e2e',
            'expectedServices' => [...$baseServices, ...$e2eOnlyServices],
            'unexpectedServices' => [MakeCoverageTestCommand::class],
        ];

        yield 'dev environment' => [
            'environment' => 'dev',
            'expectedServices' => [...$baseServices, MakeCoverageTestCommand::class],
            'unexpectedServices' => $e2eOnlyServices,
        ];
    }
}
