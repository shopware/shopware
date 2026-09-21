<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Framework\ContentSystem\Binding\Loader\YamlBindingSpecificationLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Loader\LayoutPresetSourceDirectory;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Loader\YamlLayoutPresetLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\ElementTypeSourceDirectory;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\YamlTypeLoader;
use Shopware\Core\Framework\DependencyInjection\DependencyInjectionException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @internal
 */
#[Package('framework')]
final class ContentSystemCompilerPass implements CompilerPassInterface
{
    private const STANDARD_TYPE_DIRECTORY = 'Resources/content-system/types';

    private const STANDARD_PRESET_DIRECTORY = 'Resources/content-system/presets';

    private const CORE_TYPE_DEFINITIONS_DIRECTORY = __DIR__ . '/../../ContentSystem/Layout/Type/Definitions';

    private const CORE_PRESET_DEFINITIONS_DIRECTORY = __DIR__ . '/../../ContentSystem/Layout/Preset/Definitions';

    private const CORE_PREFIX = 'Sw';

    public function process(ContainerBuilder $container): void
    {
        $hasTypeLoader = $container->hasDefinition(YamlTypeLoader::class);
        $hasBindingLoader = $container->hasDefinition(YamlBindingSpecificationLoader::class);
        $hasPresetLoader = $container->hasDefinition(YamlLayoutPresetLoader::class);

        if (!$hasTypeLoader && !$hasBindingLoader && !$hasPresetLoader) {
            return;
        }

        $typeDirectories = [];
        $presetDirectories = [];

        $this->loadTypeDirectory(self::CORE_TYPE_DEFINITIONS_DIRECTORY, 'core', self::CORE_PREFIX, $typeDirectories);
        $this->loadPresetDirectory(self::CORE_PRESET_DEFINITIONS_DIRECTORY, 'core', self::CORE_PREFIX, $presetDirectories);
        $this->loadFromBundleMetadata($container, $typeDirectories, $presetDirectories);
        $this->loadFromPlugins($container, $typeDirectories, $presetDirectories);

        if ($container->getParameter('kernel.environment') === 'dev') {
            $this->loadFromApps($container, $typeDirectories, $presetDirectories);
        }

        if ($hasTypeLoader) {
            $container->getDefinition(YamlTypeLoader::class)->setArgument('$directories', $this->toTypeDefinitions($typeDirectories));
        }

        if ($hasBindingLoader) {
            $container->getDefinition(YamlBindingSpecificationLoader::class)->setArgument('$directories', $this->toTypeDefinitions($typeDirectories));
        }

        if ($hasPresetLoader) {
            $container->getDefinition(YamlLayoutPresetLoader::class)->setArgument('$directories', $this->toPresetDefinitions($presetDirectories));
        }
    }

    /**
     * @param list<array{string, string, string}> $typeDirectories
     * @param list<array{string, string, string}> $presetDirectories
     */
    private function loadFromBundleMetadata(ContainerBuilder $container, array &$typeDirectories, array &$presetDirectories): void
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

            $this->loadTypeDirectory($metadata['path'] . '/' . self::STANDARD_TYPE_DIRECTORY, 'bundle:' . $bundleName, self::CORE_PREFIX, $typeDirectories);
            $this->loadPresetDirectory($metadata['path'] . '/' . self::STANDARD_PRESET_DIRECTORY, 'bundle:' . $bundleName, self::CORE_PREFIX, $presetDirectories);
        }
    }

    /**
     * @param list<array{string, string, string}> $typeDirectories
     * @param list<array{string, string, string}> $presetDirectories
     */
    private function loadFromPlugins(ContainerBuilder $container, array &$typeDirectories, array &$presetDirectories): void
    {
        foreach ($this->getActivePluginClasses($container) as $pluginClass => $pluginMeta) {
            $this->loadTypeDirectory($pluginMeta['path'] . '/' . $pluginClass::getContentTypeDirectory(), 'plugin:' . $pluginMeta['name'], $pluginMeta['name'], $typeDirectories);
            $this->loadPresetDirectory($pluginMeta['path'] . '/' . $pluginClass::getLayoutPresetDirectory(), 'plugin:' . $pluginMeta['name'], $pluginMeta['name'], $presetDirectories);
        }
    }

    /**
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
     * @param list<array{string, string, string}> $typeDirectories
     * @param list<array{string, string, string}> $presetDirectories
     */
    private function loadFromApps(ContainerBuilder $container, array &$typeDirectories, array &$presetDirectories): void
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
            $this->loadTypeDirectory(\sprintf('%s/%s/%s', $projectDirectory, $app['path'], self::STANDARD_TYPE_DIRECTORY), 'app:' . $app['name'], $app['name'], $typeDirectories);
            $this->loadPresetDirectory(\sprintf('%s/%s/%s', $projectDirectory, $app['path'], self::STANDARD_PRESET_DIRECTORY), 'app:' . $app['name'], $app['name'], $presetDirectories);
        }
    }

    /**
     * @param list<array{string, string, string}> $directories
     */
    private function loadTypeDirectory(string $directory, string $source, string $prefix, array &$directories): void
    {
        $directories[] = [$source, $directory, $prefix];
    }

    /**
     * @param list<array{string, string, string}> $directories
     */
    private function loadPresetDirectory(string $directory, string $source, string $prefix, array &$directories): void
    {
        $directories[] = [$source, $directory, $prefix];
    }

    /**
     * @param list<array{string, string, string}> $directories
     *
     * @return list<Definition>
     */
    private function toTypeDefinitions(array $directories): array
    {
        return array_map(
            static fn (array $directory): Definition => new Definition(ElementTypeSourceDirectory::class, $directory),
            $directories,
        );
    }

    /**
     * @param list<array{string, string, string}> $directories
     *
     * @return list<Definition>
     */
    private function toPresetDefinitions(array $directories): array
    {
        return array_map(
            static fn (array $directory): Definition => new Definition(LayoutPresetSourceDirectory::class, $directory),
            $directories,
        );
    }
}
