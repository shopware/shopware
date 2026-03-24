<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\YamlTypeLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\CompiledElementTypeDefinition;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\CompiledElementTypeDefinitionCollection;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\ContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Serialization\ElementTypeSpecificationSerializer;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
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
final class ContentSystemElementTypeCompilerPass implements CompilerPassInterface
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
        if (!$container->hasDefinition(ContentSystemElementTypeRegistry::class)) {
            return;
        }

        $collection = new CompiledElementTypeDefinitionCollection();

        $this->loadFromDirectory(self::CORE_DEFINITIONS_DIRECTORY, 'core', $collection);
        $this->loadFromBundleMetadata($container, $collection);
        $this->loadFromPlugins($container, $collection);
        $this->loadFromApps($container, $collection);

        $inlineDefinitions = [];
        foreach ($collection->all() as $compiled) {
            $inlineDefinitions[] = $this->createCompiledDefinition($compiled);
        }

        $registryServiceDefinition = $container->getDefinition(ContentSystemElementTypeRegistry::class);
        $registryServiceDefinition->setArgument(0, $inlineDefinitions);
    }

    /**
     * Active plugins excluded — loaded separately via loadFromPlugins to support custom type directories.
     */
    private function loadFromBundleMetadata(ContainerBuilder $container, CompiledElementTypeDefinitionCollection $collection): void
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

            $this->loadFromDirectory($metadata['path'] . '/' . self::STANDARD_TYPE_DIRECTORY, 'bundle:' . $bundleName, $collection);
        }
    }

    private function loadFromPlugins(ContainerBuilder $container, CompiledElementTypeDefinitionCollection $collection): void
    {
        foreach ($this->getActivePluginClasses($container) as $pluginClass => $pluginMeta) {
            $relativeDirectory = $pluginClass::getContentTypeDirectory();

            $this->loadFromDirectory($pluginMeta['path'] . '/' . $relativeDirectory, 'plugin:' . $pluginMeta['name'], $collection);
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

    private function loadFromApps(ContainerBuilder $container, CompiledElementTypeDefinitionCollection $collection): void
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
            $this->loadFromDirectory(\sprintf('%s/%s/%s', $projectDirectory, $app['path'], self::STANDARD_TYPE_DIRECTORY), 'app:' . $app['name'], $collection);
        }
    }

    private function loadFromDirectory(string $directory, string $source, CompiledElementTypeDefinitionCollection $collection): void
    {
        foreach ($this->loader->load(new Filesystem($directory)) as $specification) {
            $collection->add(new CompiledElementTypeDefinition($specification, $source));
        }
    }

    private function createCompiledDefinition(CompiledElementTypeDefinition $compiled): Definition
    {
        $specDef = $this->createSpecificationDefinition($compiled->specification);

        $compiledDef = new Definition(CompiledElementTypeDefinition::class);
        $compiledDef->setArguments([$specDef, $compiled->source]);

        return $compiledDef;
    }

    private function createSpecificationDefinition(ContentSystemElementTypeSpecification $spec): Definition
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

        $def = new Definition(ContentSystemElementTypeSpecification::class);
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
