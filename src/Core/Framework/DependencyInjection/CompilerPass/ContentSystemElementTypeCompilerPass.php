<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Framework\ContentSystem\Binding\Loader\YamlBindingSpecificationLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\ElementTypeSourceDirectory;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\YamlTypeLoader;
use Shopware\Core\Framework\DependencyInjection\DependencyInjectionException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Discovers the element-type YAML directories (core, bundles, plugins, and — in dev — apps) once and injects the
 * resulting directory set into both {@see YamlTypeLoader} and {@see YamlBindingSpecificationLoader}: the type loader
 * scans them for element-type definitions, the binding loader scans the same files for their inline `bindings:`
 * sections. Each loader receives its own {@see ElementTypeSourceDirectory} definition instances.
 *
 * @internal
 */
#[Package('framework')]
final class ContentSystemElementTypeCompilerPass implements CompilerPassInterface
{
    private const STANDARD_TYPE_DIRECTORY = 'Resources/content-system/types';

    private const CORE_DEFINITIONS_DIRECTORY = __DIR__ . '/../../ContentSystem/Layout/Type/Definitions';

    private const CORE_PREFIX = 'Sw';

    public function process(ContainerBuilder $container): void
    {
        $hasTypeLoader = $container->hasDefinition(YamlTypeLoader::class);
        $hasBindingLoader = $container->hasDefinition(YamlBindingSpecificationLoader::class);

        if (!$hasTypeLoader && !$hasBindingLoader) {
            return;
        }

        $directories = [];

        $this->loadFromDirectory(self::CORE_DEFINITIONS_DIRECTORY, 'core', self::CORE_PREFIX, $directories);
        $this->loadFromBundleMetadata($container, $directories);
        $this->loadFromPlugins($container, $directories);

        // In prod, app types/bindings are loaded from the database by DatabaseTypeLoader / DatabaseBindingSpecificationLoader instead
        if ($container->getParameter('kernel.environment') === 'dev') {
            $this->loadFromApps($container, $directories);
        }

        if ($hasTypeLoader) {
            $container->getDefinition(YamlTypeLoader::class)->setArgument('$directories', $this->toDefinitions($directories));
        }

        if ($hasBindingLoader) {
            $container->getDefinition(YamlBindingSpecificationLoader::class)->setArgument('$directories', $this->toDefinitions($directories));
        }
    }

    /**
     * Active plugins excluded — loaded separately via loadFromPlugins to support custom type directories.
     *
     * @param list<array{string, string, string}> $directories
     */
    private function loadFromBundleMetadata(ContainerBuilder $container, array &$directories): void
    {
        $bundleMetadata = $container->getParameter('kernel.bundles_metadata');
        if (!\is_array($bundleMetadata)) {
            throw DependencyInjectionException::bundlesMetadataIsNotAnArray();
        }

        $activePlugins = $container->getParameter('kernel.active_plugins');
        if (!\is_array($activePlugins)) {
            throw DependencyInjectionException::parameterHasWrongType('kernel.active_plugins', 'array', get_debug_type($activePlugins));
        }

        $pluginBundleNames = [];
        foreach ($activePlugins as $pluginMeta) {
            if (\is_array($pluginMeta) && isset($pluginMeta['name']) && \is_string($pluginMeta['name'])) {
                $pluginBundleNames[$pluginMeta['name']] = true;
            }
        }

        foreach ($bundleMetadata as $bundleName => $metadata) {
            if (isset($pluginBundleNames[$bundleName])) {
                continue;
            }

            $this->loadFromDirectory($metadata['path'] . '/' . self::STANDARD_TYPE_DIRECTORY, 'bundle:' . $bundleName, self::CORE_PREFIX, $directories);
        }
    }

    /**
     * @param list<array{string, string, string}> $directories
     */
    private function loadFromPlugins(ContainerBuilder $container, array &$directories): void
    {
        foreach ($this->getActivePluginClasses($container) as $pluginClass => $pluginMeta) {
            $relativeDirectory = $pluginClass::getContentTypeDirectory();

            $this->loadFromDirectory($pluginMeta['path'] . '/' . $relativeDirectory, 'plugin:' . $pluginMeta['name'], $pluginMeta['name'], $directories);
        }
    }

    /**
     * Narrows the untyped kernel.active_plugins parameter to a typed array
     * so callers can safely call static methods on the plugin class strings.
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
     * DBAL exceptions are silently swallowed because the compiler pass may run
     * before the database exists (fresh install, CI).
     *
     * @param list<array{string, string, string}> $directories
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
            $this->loadFromDirectory(\sprintf('%s/%s/%s', $projectDirectory, $app['path'], self::STANDARD_TYPE_DIRECTORY), 'app:' . $app['name'], $app['name'], $directories);
        }
    }

    /**
     * @param list<array{string, string, string}> $directories
     */
    private function loadFromDirectory(string $directory, string $source, string $prefix, array &$directories): void
    {
        $directories[] = [$source, $directory, $prefix];
    }

    /**
     * Maps the discovered directory triples to fresh {@see ElementTypeSourceDirectory} definitions, so each loader
     * argument gets its own definition instances rather than sharing them across the two services.
     *
     * @param list<array{string, string, string}> $directories
     *
     * @return list<Definition>
     */
    private function toDefinitions(array $directories): array
    {
        return array_map(
            static fn (array $directory): Definition => new Definition(ElementTypeSourceDirectory::class, $directory),
            $directories,
        );
    }
}
