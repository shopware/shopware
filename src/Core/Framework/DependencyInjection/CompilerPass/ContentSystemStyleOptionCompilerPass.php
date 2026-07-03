<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Loader\StyleOptionSourceDirectory;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Loader\YamlStyleOptionLoader;
use Shopware\Core\Framework\DependencyInjection\DependencyInjectionException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Discovers style option YAML directories from core, bundles, plugins, and (in dev) active apps, and
 * injects them into the YamlStyleOptionLoader.
 *
 * @internal
 */
#[Package('framework')]
final class ContentSystemStyleOptionCompilerPass implements CompilerPassInterface
{
    private const STANDARD_STYLE_OPTION_DIRECTORY = 'Resources/content-system/style-options';

    private const CORE_DEFINITIONS_DIRECTORY = __DIR__ . '/../../ContentSystem/Layout/Element/Style/Definitions';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(YamlStyleOptionLoader::class)) {
            return;
        }

        $directories = [];

        $this->addDirectory(self::CORE_DEFINITIONS_DIRECTORY, 'core', $directories);
        $this->loadFromBundleMetadata($container, $directories);

        // In prod, app options are loaded from the database by DatabaseStyleOptionLoader instead
        if ($container->getParameter('kernel.environment') === 'dev') {
            $this->loadFromApps($container, $directories);
        }

        $container->getDefinition(YamlStyleOptionLoader::class)->setArgument('$directories', $directories);
    }

    /**
     * @param list<Definition> $directories
     */
    private function loadFromBundleMetadata(ContainerBuilder $container, array &$directories): void
    {
        $bundleMetadata = $container->getParameter('kernel.bundles_metadata');
        if (!\is_array($bundleMetadata)) {
            throw DependencyInjectionException::bundlesMetadataIsNotAnArray();
        }

        $pluginBundleNames = $this->getActivePluginBundleNames($container);

        foreach ($bundleMetadata as $bundleName => $metadata) {
            if (!\is_array($metadata) || !isset($metadata['path']) || !\is_string($metadata['path'])) {
                continue;
            }

            // Plugins and bundles share the fixed convention directory; only the source label differs
            $source = (isset($pluginBundleNames[$bundleName]) ? 'plugin:' : 'bundle:') . $bundleName;

            $this->addDirectory($metadata['path'] . '/' . self::STANDARD_STYLE_OPTION_DIRECTORY, $source, $directories);
        }
    }

    /**
     * @return array<string, true>
     */
    private function getActivePluginBundleNames(ContainerBuilder $container): array
    {
        $activePlugins = $container->getParameter('kernel.active_plugins');
        if (!\is_array($activePlugins)) {
            throw DependencyInjectionException::parameterHasWrongType('kernel.active_plugins', 'array', get_debug_type($activePlugins));
        }

        $names = [];
        foreach ($activePlugins as $pluginMeta) {
            if (\is_array($pluginMeta) && isset($pluginMeta['name']) && \is_string($pluginMeta['name'])) {
                $names[$pluginMeta['name']] = true;
            }
        }

        return $names;
    }

    /**
     * DBAL exceptions are silently swallowed because the compiler pass may run before the database
     * exists (fresh install, CI).
     *
     * @param list<Definition> $directories
     */
    private function loadFromApps(ContainerBuilder $container, array &$directories): void
    {
        $connection = $container->get(Connection::class);

        try {
            $apps = $connection->fetchAllAssociative('SELECT `path`, `name` FROM `app` WHERE `active` = 1');
        } catch (Exception) {
            return;
        }

        $projectDirectory = $container->getParameter('kernel.project_dir');
        if (!\is_string($projectDirectory)) {
            throw DependencyInjectionException::projectDirNotInContainer();
        }

        foreach ($apps as $app) {
            $this->addDirectory(\sprintf('%s/%s/%s', $projectDirectory, $app['path'], self::STANDARD_STYLE_OPTION_DIRECTORY), 'app:' . $app['name'], $directories);
        }
    }

    /**
     * @param list<Definition> $directories
     */
    private function addDirectory(string $directory, string $source, array &$directories): void
    {
        $directories[] = new Definition(StyleOptionSourceDirectory::class, [$source, $directory]);
    }
}
