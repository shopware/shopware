<?php

declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationSource;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[Package('framework')]
abstract class AbstractMigrationReplacementCompilerPass implements CompilerPassInterface
{
    private const MAJOR_VERSIONS = ['V6_3', 'V6_4', 'V6_5', 'V6_6', 'V6_7', 'V6_8'];

    public function process(ContainerBuilder $container): void
    {
        $migrationPath = $this->getMigrationPath();
        // configure migration directories
        foreach (self::MAJOR_VERSIONS as $major) {
            $versionedMigrationPath = $migrationPath . '/' . $major;

            if (\is_dir($versionedMigrationPath)) {
                $migrationSource = $container->getDefinition(MigrationSource::class . '.core.' . $major);
                $migrationSource->addMethodCall('addDirectory', [$versionedMigrationPath, $this->getMigrationNamespace() . '\\' . $major]);
            }
        }
    }

    abstract protected function getMigrationPath(): string;

    abstract protected function getMigrationNamespace(): string;
}
