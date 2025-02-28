<?php declare(strict_types=1);

namespace Shopware\Storefront\DependencyInjection;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationSource;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[Package('framework')]
class StorefrontMigrationReplacementCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $migrationPath = \dirname(__DIR__) . '/Migration';

        $majors = ['V6_3', 'V6_4', 'V6_5', 'V6_6', '6_7', 'V6_8'];
        foreach ($majors as $major) {
            $migrationPathV5 = $migrationPath . '/' . $major;

            if (\is_dir($migrationPathV5)) {
                $migrationSource = $container->getDefinition(MigrationSource::class . '.core.' . $major);
                $migrationSource->addMethodCall('addDirectory', [$migrationPathV5, 'Shopware\Storefront\Migration\\' . $major]);
            }
        }
    }
}
