<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Migration\MigrationSource;
use Shopware\Storefront\DependencyInjection\StorefrontMigrationReplacementCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
class StorefrontMigrationReplacementCompilerPassTest extends TestCase
{
    public function testProcessing(): void
    {
        $container = new ContainerBuilder();
        $container->register(MigrationSource::class . '.core.V6_3', MigrationSource::class)->setPublic(true);
        $container->register(MigrationSource::class . '.core.V6_4', MigrationSource::class)->setPublic(true);
        $container->register(MigrationSource::class . '.core.V6_5', MigrationSource::class)->setPublic(true);
        $container->register(MigrationSource::class . '.core.V6_6', MigrationSource::class)->setPublic(true);

        $container->addCompilerPass(new StorefrontMigrationReplacementCompilerPass());
        $container->compile();

        $calls = $container->getDefinition(MigrationSource::class . '.core.V6_3')->getMethodCalls();
        static::assertCount(1, $calls);

        static::assertSame('addDirectory', $calls[0][0]);
        static::assertStringContainsString('Migration/V6_3', $calls[0][1][0]);
        static::assertSame('Shopware\Storefront\Migration\V6_3', $calls[0][1][1]);
    }
}
