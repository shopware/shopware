<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\YamlTypeLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\ContentElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Serialization\ElementTypeSpecificationSerializer;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\CopilotSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertySpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertyType;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\SlotSpecification;
use Shopware\Core\Framework\DependencyInjection\DependencyInjectionException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('framework')]
final class ElementTypeCompilerPass implements CompilerPassInterface
{
    private const STANDARD_TYPE_DIRECTORY = 'Resources/content-system/types';

    private const CORE_DEFINITIONS_DIRECTORY = __DIR__ . '/../../ContentSystem/Layout/Type/Definitions';

    public function __construct(
        private readonly YamlTypeLoader $loader,
    ) {
    }

    public static function withDefaultLoader(): self
    {
        return new self(
            new YamlTypeLoader(
                new ElementTypeSpecificationSerializer(),
                Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()
            )
        );
    }

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(ContentElementTypeRegistry::class)) {
            return;
        }

        $allSpecifications = [];

        $this->loadFromDirectory(self::CORE_DEFINITIONS_DIRECTORY, $allSpecifications);
        $this->loadFromBundleMetadata($container, $allSpecifications);
        $this->loadFromPlugins($container, $allSpecifications);
        $this->loadFromApps($container, $allSpecifications);

        $inlineDefinitions = [];
        foreach ($allSpecifications as $spec) {
            $inlineDefinitions[] = $this->createInlineDefinition($spec);
        }

        $registryServiceDefinition = $container->getDefinition(ContentElementTypeRegistry::class);
        $registryServiceDefinition->setArgument(0, $inlineDefinitions);
    }

    /**
     * Loads from all bundles using the standard path. Skips active plugins
     * (handled separately in loadFromPlugins where they can override the path).
     *
     * @param list<ContentElementTypeSpecification> $allSpecifications
     */
    private function loadFromBundleMetadata(ContainerBuilder $container, array &$allSpecifications): void
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

            $this->loadFromDirectory($metadata['path'] . '/' . self::STANDARD_TYPE_DIRECTORY, $allSpecifications);
        }
    }

    /**
     * Loads from active plugins using the (potentially overridden) type directory.
     *
     * @param list<ContentElementTypeSpecification> $allSpecifications
     */
    private function loadFromPlugins(ContainerBuilder $container, array &$allSpecifications): void
    {
        foreach ($this->getActivePluginClasses($container) as $pluginClass => $pluginMeta) {
            $relativeDirectory = $pluginClass::getContentTypeDirectory();

            $this->loadFromDirectory($pluginMeta['path'] . '/' . $relativeDirectory, $allSpecifications);
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
     * Loads app types from filesystem in dev environment only.
     * Follows the established pattern from TwigLoaderConfigCompilerPass.
     *
     * @param list<ContentElementTypeSpecification> $allSpecifications
     */
    private function loadFromApps(ContainerBuilder $container, array &$allSpecifications): void
    {
        if ($container->getParameter('kernel.environment') !== 'dev') {
            return;
        }

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
            $this->loadFromDirectory(\sprintf('%s/%s/%s', $projectDirectory, $app['path'], self::STANDARD_TYPE_DIRECTORY), $allSpecifications);
        }
    }

    /**
     * @param list<ContentElementTypeSpecification> $allSpecifications
     */
    private function loadFromDirectory(string $directory, array &$allSpecifications): void
    {
        foreach ($this->loader->load(new Filesystem($directory)) as $specification) {
            $allSpecifications[] = $specification;
        }
    }

    private function createInlineDefinition(ContentElementTypeSpecification $spec): Definition
    {
        $schema = $spec->toSchema();

        $copilotDef = new Definition(CopilotSpecification::class);
        $copilotDef->setArguments([
            $schema['copilot']['summary'] ?? '',
            $schema['copilot']['hints'] ?? [],
        ]);

        $propertyDefs = [];
        foreach ($schema['properties'] as $key => $propSchema) {
            $typeDef = new Definition(PropertyType::class);
            $typeDef->setArguments([
                $propSchema['type'],
                $propSchema['translatable'] ?? false,
                $propSchema['enum'] ?? null,
                $propSchema['default'] ?? null,
            ]);

            $propDef = new Definition(PropertySpecification::class);
            $propDef->setArguments([
                $key,
                $typeDef,
                $propSchema['required'] ?? false,
                $propSchema['title'] ?? '',
                $propSchema['description'] ?? '',
                $propSchema['adminUI'] ?? null,
            ]);
            $propertyDefs[$key] = $propDef;
        }

        $slotDefs = [];
        foreach ($schema['slots'] as $slotSchema) {
            $slotDef = new Definition(SlotSpecification::class);
            $slotDef->setArguments([
                $slotSchema['name'],
                $slotSchema['maxElements'] ?? null,
                $slotSchema['allowList'] ?? [],
                $slotSchema['description'] ?? '',
            ]);
            $slotDefs[] = $slotDef;
        }

        $def = new Definition(ContentElementTypeSpecification::class);
        $def->setArguments([
            $schema['name'],
            $schema['label'],
            $schema['description'] ?? '',
            $schema['vendor'] ?? '',
            $schema['icon'] ?? null,
            $schema['category'] ?? null,
            $copilotDef,
            $propertyDefs,
            $slotDefs,
        ]);

        return $def;
    }
}
