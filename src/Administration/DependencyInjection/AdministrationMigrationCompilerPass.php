<?php declare(strict_types=1);

namespace Shopware\Administration\DependencyInjection;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationSource;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[Package('framework')]
class AdministrationMigrationCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $migrationPath = \dirname(__DIR__) . '/Migration';

        // configure migration directories
        $majors = ['V6_4', 'V6_5', 'V6_6', 'V6_7'];
        foreach ($majors as $major) {
            $migrationPathV5 = $migrationPath . '/' . $major;

            if (\is_dir($migrationPathV5)) {
                $migrationSource = $container->getDefinition(MigrationSource::class . '.core.' . $major);
                $migrationSource->addMethodCall('addDirectory', [$migrationPathV5, 'Shopware\Administration\Migration\\' . $major]);
            }
        }
    }
}
