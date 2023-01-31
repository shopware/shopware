<?php declare(strict_types=1);

namespace Shopware\Administration\Test\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Shopware\Administration\DependencyInjection\AdministrationMigrationCompilerPass;
use Shopware\Core\Framework\Migration\MigrationSource;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
class AdminMigrationCompilerPassTest extends TestCase
{
    public function testProcessing(): void
    {
        $container = new ContainerBuilder();
        $container->register(MigrationSource::class . '.core.V6_4', MigrationSource::class)->setPublic(true);

        $container->addCompilerPass(new AdministrationMigrationCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 0);
        $container->compile(false);

        $calls = $container->getDefinition(MigrationSource::class . '.core.V6_4')->getMethodCalls();
        static::assertCount(1, $calls);

        static::assertSame('addDirectory', $calls[0][0]);
        static::assertStringContainsString('Migration/V6_4', $calls[0][1][0]);
        static::assertSame('Shopware\Administration\Migration\V6_4', $calls[0][1][1]);
    }
}
