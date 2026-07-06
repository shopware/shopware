<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Framework\ContentSystem\Binding\Loader\BindingSpecificationSourceDirectory;
use Shopware\Core\Framework\ContentSystem\Binding\Loader\YamlBindingSpecificationLoader;
use Shopware\Core\Framework\DependencyInjection\DependencyInjectionException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Injects the discovered element-type directories into {@see YamlBindingSpecificationLoader}, scanned for inline
 * `bindings` sections whose implicit type names resolve with each directory's prefix. Mirrors
 * {@see ContentSystemElementTypeCompilerPass}.
 *
 * @internal
 */
#[Package('framework')]
final class ContentSystemBindingSpecificationCompilerPass implements CompilerPassInterface
{
    private const STANDARD_TYPE_DIRECTORY = 'Resources/content-system/types';

    private const CORE_TYPE_DEFINITIONS_DIRECTORY = __DIR__ . '/../../ContentSystem/Layout/Type/Definitions';

    private const CORE_PREFIX = 'Sw';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(YamlBindingSpecificationLoader::class)) {
            return;
        }

        $directories = [];

        $this->addDirectory(self::CORE_TYPE_DEFINITIONS_DIRECTORY, 'core', self::CORE_PREFIX, $directories);
        $this->loadTypesFromBundleMetadata($container, $directories);
        $this->loadTypesFromPlugins($container, $directories);

        if ($container->getParameter('kernel.environment') === 'dev') {
            $this->loadTypesFromApps($container, $directories);
        }

        $container->getDefinition(YamlBindingSpecificationLoader::class)->setArgument('$directories', $directories);
    }

    /**
     * Non-plugin bundles only; active plugins are scanned separately in loadTypesFromPlugins to honour their
     * customizable content-type directory, mirroring ContentSystemElementTypeCompilerPass.
     *
     * @param list<Definition> $directories
     */
    private function loadTypesFromBundleMetadata(ContainerBuilder $container, array &$directories): void
    {
        $bundleMetadata = $container->getParameter('kernel.bundles_metadata');
        if (!\is_array($bundleMetadata)) {
            throw DependencyInjectionException::bundlesMetadataIsNotAnArray();
        }

        $pluginBundleNames = $this->getActivePluginBundleNames($container);

        foreach ($bundleMetadata as $bundleName => $metadata) {
            if (isset($pluginBundleNames[$bundleName])) {
                continue;
            }

            if (!\is_array($metadata) || !isset($metadata['path']) || !\is_string($metadata['path'])) {
                continue;
            }

            $this->addDirectory($metadata['path'] . '/' . self::STANDARD_TYPE_DIRECTORY, 'bundle:' . $bundleName, self::CORE_PREFIX, $directories);
        }
    }

    /**
     * @param list<Definition> $directories
     */
    private function loadTypesFromPlugins(ContainerBuilder $container, array &$directories): void
    {
        foreach ($this->getActivePluginClasses($container) as $pluginClass => $pluginMeta) {
            $relativeDirectory = $pluginClass::getContentTypeDirectory();

            $this->addDirectory($pluginMeta['path'] . '/' . $relativeDirectory, 'plugin:' . $pluginMeta['name'], $pluginMeta['name'], $directories);
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
     * Narrows the untyped kernel.active_plugins parameter to a typed array so callers can safely call static
     * methods on the plugin class strings, mirroring ContentSystemElementTypeCompilerPass.
     *
     * @return array<class-string, array{name: string, path: string, class: string}>
     */
    private function getActivePluginClasses(ContainerBuilder $container): array
    {
        $activePlugins = $container->getParameter('kernel.active_plugins');
        if (!\is_array($activePlugins)) {
            return [];
        }

        $result = [];

        foreach ($activePlugins as $pluginClass => $pluginMeta) {
            if (!\is_string($pluginClass) || !class_exists($pluginClass)) {
                throw DependencyInjectionException::parameterHasWrongType(
                    'kernel.active_plugins',
                    'array<class-string, array>',
                    \sprintf('entry key "%s" is not a valid class', $pluginClass)
                );
            }

            if (
                !\is_array($pluginMeta)
                || !isset($pluginMeta['path'], $pluginMeta['name'], $pluginMeta['class'])
                || !\is_string($pluginMeta['path'])
                || !\is_string($pluginMeta['name'])
                || !\is_string($pluginMeta['class'])
            ) {
                throw DependencyInjectionException::parameterHasWrongType(
                    'kernel.active_plugins',
                    'array{name: string, path: string, class: string}',
                    \sprintf('entry for "%s" has missing or invalid metadata', $pluginClass)
                );
            }

            $result[$pluginClass] = [
                'name' => $pluginMeta['name'],
                'path' => $pluginMeta['path'],
                'class' => $pluginMeta['class'],
            ];
        }

        return $result;
    }

    /**
     * The app's element-type directory scanned for inline `bindings` sections, with the app name as the implicit
     * type prefix. DBAL exceptions are silently swallowed because the compiler pass may run before the database
     * exists (fresh install, CI).
     *
     * @param list<Definition> $directories
     */
    private function loadTypesFromApps(ContainerBuilder $container, array &$directories): void
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
            $this->addDirectory(\sprintf('%s/%s/%s', $projectDirectory, $app['path'], self::STANDARD_TYPE_DIRECTORY), 'app:' . $app['name'], $app['name'], $directories);
        }
    }

    /**
     * @param list<Definition> $directories
     */
    private function addDirectory(string $directory, string $source, string $prefix, array &$directories): void
    {
        $directories[] = new Definition(BindingSpecificationSourceDirectory::class, [$source, $directory, $prefix]);
    }
}
