<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationSource;
use Shopware\Elasticsearch\DependencyInjection\ElasticsearchMigrationCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ElasticsearchMigrationCompilerPass::class)]
class ElasticsearchMigrationCompilerPassTest extends TestCase
{
    public function testCompilerPass(): void
    {
        $container = new ContainerBuilder();
        $container->register(MigrationSource::class . '.core.V6_5', MigrationSource::class)->setPublic(true);
        $container->register(MigrationSource::class . '.core.V6_7', MigrationSource::class)->setPublic(true);

        $container->addCompilerPass(new ElasticsearchMigrationCompilerPass());
        $container->compile();

        foreach (['V6_5', 'V6_7'] as $version) {
            $calls = $container->getDefinition(MigrationSource::class . '.core.' . $version)->getMethodCalls();
            static::assertCount(1, $calls);

            static::assertSame('addDirectory', $calls[0][0]);
            static::assertStringContainsString('Migration/' . $version, $calls[0][1][0]);
            static::assertSame('Shopware\Elasticsearch\Migration\\' . $version, $calls[0][1][1]);
        }
    }
}
