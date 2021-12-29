<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Shopware\Core\Framework\Migration\MigrationSource;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @deprecated tag:v6.5.0 - Use own migration source instead
 */
class AddCoreMigrationPathCompilerPass implements CompilerPassInterface
{
    private string $path;

    private string $namespace;

    public function __construct(string $path, string $namespace)
    {
        $this->path = $path;
        $this->namespace = $namespace;
    }

    public function process(ContainerBuilder $container): void
    {
        $container->getDefinition(MigrationSource::class . '.core')
            ->addMethodCall('addDirectory', [$this->path, $this->namespace]);
    }
}
