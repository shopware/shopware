<?php declare(strict_types=1);

namespace Shopware\Core\Framework;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\ExtensionRegistry;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\ActionEventCompilerPass;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\EntityCompilerPass;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\FeatureFlagCompilerPass;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\SalesChannelEntityCompilerPass;
use Shopware\Core\Framework\DependencyInjection\FrameworkExtension;
use Shopware\Core\Framework\Migration\MigrationCompilerPass;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

class Framework extends Bundle
{
    public const VERSION = '___VERSION___';
    public const VERSION_TEXT = '___VERSION_TEXT___';
    public const REVISION = '___REVISION___';

    protected $name = 'Shopware';

    /**
     * {@inheritdoc}
     */
    public function getContainerExtension(): Extension
    {
        return new FrameworkExtension();
    }

    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container): void
    {
        $container->setParameter('locale', 'en_GB');

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/DependencyInjection/'));
        $loader->load('services.xml');
        $loader->load('api.xml');
        $loader->load('attribute.xml');
        $loader->load('data-abstraction-layer.xml');
        $loader->load('demodata.xml');
        $loader->load('event.xml');
        $loader->load('filesystem.xml');
        $loader->load('message-queue.xml');
        $loader->load('plugin.xml');
        $loader->load('rule.xml');
        $loader->load('scheduled-task.xml');
        $loader->load('store.xml');
        $loader->load('language.xml');

        $container->addCompilerPass(new FeatureFlagCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION);
        $container->addCompilerPass(new EntityCompilerPass());
        $container->addCompilerPass(new SalesChannelEntityCompilerPass());
        $container->addCompilerPass(new MigrationCompilerPass(), PassConfig::TYPE_AFTER_REMOVING);
        $container->addCompilerPass(new ActionEventCompilerPass());

        parent::build($container);
    }

    public function boot(): void
    {
        parent::boot();

        /** @var ExtensionRegistry $registry */
        $registry = $this->container->get(ExtensionRegistry::class);
        foreach ($registry->getExtensions() as $extension) {
            /** @var EntityDefinition $definition */
            $definition = $extension->getDefinitionClass();
            $definition::addExtension($extension);
        }
    }

    protected function registerMigrationPath(ContainerBuilder $container): void
    {
        $directories = $container->getParameter('migration.directories');
        $directories['Shopware\Core\Migration'] = __DIR__ . '/../Migration';

        $container->setParameter('migration.directories', $directories);
    }

    protected function registerFilesystem(ContainerBuilder $container, string $key): void
    {
        // empty body intended to prevent circular filesystem references
    }
}
