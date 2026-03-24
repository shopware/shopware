<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\YamlTypeLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\ContentElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Serialization\ElementTypeSpecificationSerializer;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\CopilotSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertySpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertyType;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\SlotSpecification;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\ElementTypeCompilerPass;
use Shopware\Core\Framework\DependencyInjection\DependencyInjectionException;
use Shopware\Core\Framework\Plugin;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[CoversClass(ElementTypeCompilerPass::class)]
class ElementTypeCompilerPassTest extends TestCase
{
    /**
     * Fixtures root directory. Sub-directories mirror the directory layout expected by the compiler pass.
     *
     * fixtures/
     *   bundle-a/Resources/content-system/types/  — standard bundle path (type: Sw:Test:Element)
     *   test-plugin/Resources/content-system/types/ — default plugin path (type: Sw:Plugin:Element)
     *   test-plugin-custom/custom-types/           — custom plugin path (type: Sw:CustomPlugin:Element)
     *   apps/test-app/Resources/content-system/types/ — app path (type: Sw:App:Element)
     */
    private const FIXTURES_DIR = __DIR__ . '/fixtures';

    private ElementTypeCompilerPass $pass;

    protected function setUp(): void
    {
        $this->pass = new ElementTypeCompilerPass(
            new YamlTypeLoader(
                new ElementTypeSpecificationSerializer(),
                Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator(),
            )
        );
    }

    #[TestDox('returns early without loading any specs when registry definition is missing')]
    public function testProcessReturnsEarlyWhenRegistryIsMissing(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles_metadata', []);
        $container->setParameter('kernel.active_plugins', []);
        $container->setParameter('kernel.environment', 'prod');

        // No registry definition — must not throw
        $this->pass->process($container);

        static::assertFalse($container->hasDefinition(ContentElementTypeRegistry::class));
    }

    #[TestDox('scans non-plugin bundles using the standard type directory path')]
    public function testScansNonPluginBundlesForTypes(): void
    {
        $container = $this->buildContainer('prod');
        $container->setParameter('kernel.bundles_metadata', [
            // bundle-a ships 'Sw:Test:Element' at Resources/content-system/types/
            'BundleA' => ['path' => self::FIXTURES_DIR . '/bundle-a'],
        ]);
        $container->setParameter('kernel.active_plugins', []);

        $registryDef = $container->getDefinition(ContentElementTypeRegistry::class);
        $this->pass->process($container);

        $names = $this->extractTypeNames($registryDef->getArgument(0));
        static::assertContains('Sw:Test:Element', $names, 'Bundle scan must include fixture type');
    }

    #[TestDox('skips active-plugin bundles during bundle-metadata loading')]
    public function testSkipsActivePluginBundlesDuringBundleScan(): void
    {
        $container = $this->buildContainer('prod');
        $container->setParameter('kernel.bundles_metadata', [
            // MyPlugin path points at the fixture bundle, but it is an active plugin → skipped
            'MyPlugin' => ['path' => self::FIXTURES_DIR . '/bundle-a'],
        ]);
        $container->setParameter('kernel.active_plugins', [
            FixturePlugin::class => [
                // Plugin path is non-existent, so no types come from here either
                'name' => 'MyPlugin',
                'path' => '/non-existent-plugin-path',
                'class' => FixturePlugin::class,
            ],
        ]);

        $registryDef = $container->getDefinition(ContentElementTypeRegistry::class);
        $this->pass->process($container);

        $names = $this->extractTypeNames($registryDef->getArgument(0));
        static::assertNotContains('Sw:Test:Element', $names, 'Plugin bundles must not be scanned via bundle metadata');
    }

    #[TestDox('loads active plugins from their configured type directory')]
    public function testLoadsPluginTypesFromConfiguredDirectory(): void
    {
        $container = $this->buildContainer('prod');
        $container->setParameter('kernel.bundles_metadata', []);
        $container->setParameter('kernel.active_plugins', [
            // test-plugin ships 'Sw:Plugin:Element' at Resources/content-system/types/
            FixturePlugin::class => [
                'name' => 'FixturePlugin',
                'path' => self::FIXTURES_DIR . '/test-plugin',
                'class' => FixturePlugin::class,
            ],
        ]);

        $registryDef = $container->getDefinition(ContentElementTypeRegistry::class);
        $this->pass->process($container);

        $names = $this->extractTypeNames($registryDef->getArgument(0));
        static::assertContains('Sw:Plugin:Element', $names, 'Plugin scan must include fixture type');
    }

    #[TestDox('uses the overridden path for plugins with a custom type directory')]
    public function testPluginWithCustomTypeDirectoryIsRespected(): void
    {
        $container = $this->buildContainer('prod');
        $container->setParameter('kernel.bundles_metadata', []);
        $container->setParameter('kernel.active_plugins', [
            // test-plugin-custom ships 'Sw:CustomPlugin:Element' at custom-types/
            FixturePluginWithCustomTypeDir::class => [
                'name' => 'FixturePluginWithCustomTypeDir',
                'path' => self::FIXTURES_DIR . '/test-plugin-custom',
                'class' => FixturePluginWithCustomTypeDir::class,
            ],
        ]);

        $registryDef = $container->getDefinition(ContentElementTypeRegistry::class);
        $this->pass->process($container);

        $names = $this->extractTypeNames($registryDef->getArgument(0));
        static::assertContains('Sw:CustomPlugin:Element', $names, 'Plugin with custom dir must find fixture type');
    }

    #[TestDox('loads app types from filesystem in dev environment')]
    public function testAppLoadingInDevEnvironment(): void
    {
        $container = $this->buildContainer('dev');
        $container->setParameter('kernel.bundles_metadata', []);
        $container->setParameter('kernel.active_plugins', []);
        // project_dir + app_path + Resources/content-system/types = fixture app dir
        $container->setParameter('kernel.project_dir', self::FIXTURES_DIR . '/apps');

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')
            ->willReturn([
                ['path' => 'test-app', 'name' => 'TestApp'],
            ]);
        $container->set(Connection::class, $connection);

        $registryDef = $container->getDefinition(ContentElementTypeRegistry::class);
        $this->pass->process($container);

        $names = $this->extractTypeNames($registryDef->getArgument(0));
        static::assertContains('Sw:App:Element', $names, 'App scan in dev must include fixture type');
    }

    #[TestDox('skips app loading when environment is not dev')]
    public function testAppLoadingIsSkippedInProductionEnvironment(): void
    {
        $container = $this->buildContainer('prod');
        $container->setParameter('kernel.bundles_metadata', []);
        $container->setParameter('kernel.active_plugins', []);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchAllAssociative');
        $container->set(Connection::class, $connection);

        $this->pass->process($container);
    }

    #[TestDox('swallows DB exception during app loading')]
    public function testAppLoadingContinuesWhenDbQueryFails(): void
    {
        $container = $this->buildContainer('dev');
        $container->setParameter('kernel.bundles_metadata', []);
        $container->setParameter('kernel.active_plugins', []);
        $container->setParameter('kernel.project_dir', '/project');

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willThrowException(static::createStub(DbalException::class));
        $container->set(Connection::class, $connection);

        // Must not throw — DB failures are silently swallowed
        $this->pass->process($container);

        // Verify the registry was still populated (from core definitions)
        $registryDef = $container->getDefinition(ContentElementTypeRegistry::class);
        static::assertIsArray($registryDef->getArgument(0));
    }

    #[TestDox('creates inline definition with correct top-level spec fields and copilot')]
    public function testInlineDefinitionTopLevelFields(): void
    {
        $testDef = $this->loadFixtureDefinition();

        static::assertSame(ContentElementTypeSpecification::class, $testDef->getClass());

        [$name, $label, $description, $vendor, $icon, $category, $copilotDef] = $testDef->getArguments();

        static::assertSame('Sw:Test:Element', $name);
        static::assertSame('Test Element', $label);
        static::assertSame('A test element for unit tests.', $description);
        static::assertSame('shopware AG', $vendor);
        static::assertSame('default', $icon);
        static::assertSame('content', $category);

        static::assertInstanceOf(Definition::class, $copilotDef);
        static::assertSame(CopilotSpecification::class, $copilotDef->getClass());
        [$summary, $hints] = $copilotDef->getArguments();
        static::assertSame('A summary', $summary);
        static::assertSame(['hint one'], $hints);
    }

    #[TestDox('creates inline definition with correct property sub-definitions')]
    public function testInlineDefinitionPropertyStructure(): void
    {
        $testDef = $this->loadFixtureDefinition();

        $propertyDefs = $testDef->getArguments()[7];
        static::assertIsArray($propertyDefs);
        static::assertArrayHasKey('title', $propertyDefs);

        $propDef = $propertyDefs['title'];
        static::assertInstanceOf(Definition::class, $propDef);
        static::assertSame(PropertySpecification::class, $propDef->getClass());
        [$propKey, $typeDef, $required, $propTitle] = $propDef->getArguments();
        static::assertSame('title', $propKey);
        static::assertFalse($required);
        static::assertSame('Title', $propTitle);

        static::assertInstanceOf(Definition::class, $typeDef);
        static::assertSame(PropertyType::class, $typeDef->getClass());
        [$type, $translatable, $enum, $default] = $typeDef->getArguments();
        static::assertSame('string', $type);
        static::assertTrue($translatable);
        static::assertNull($enum);
        static::assertNull($default);
    }

    #[TestDox('creates inline definition with correct slot sub-definitions')]
    public function testInlineDefinitionSlotStructure(): void
    {
        $testDef = $this->loadFixtureDefinition();

        $slotDefs = $testDef->getArguments()[8];
        static::assertIsArray($slotDefs);
        static::assertCount(1, $slotDefs);

        $slotDef = $slotDefs[0];
        static::assertInstanceOf(Definition::class, $slotDef);
        static::assertSame(SlotSpecification::class, $slotDef->getClass());
        [$slotName, $maxElements, $allowList, $slotDesc] = $slotDef->getArguments();
        static::assertSame('default', $slotName);
        static::assertSame(5, $maxElements);
        static::assertSame(['text-block', 'image'], $allowList);
        static::assertSame('Main slot', $slotDesc);
    }

    #[TestDox('throws when kernel.bundles_metadata is not an array')]
    public function testThrowsWhenBundlesMetadataIsNotAnArray(): void
    {
        $container = $this->buildContainer('prod');
        $container->setParameter('kernel.bundles_metadata', 'not-an-array');
        $container->setParameter('kernel.active_plugins', []);

        $this->expectExceptionObject(DependencyInjectionException::bundlesMetadataIsNotAnArray());

        $this->pass->process($container);
    }

    #[TestDox('throws when a plugin entry key is not a valid class-string')]
    public function testThrowsWhenPluginKeyIsNotAValidClass(): void
    {
        $container = $this->buildContainer('prod');
        $container->setParameter('kernel.bundles_metadata', []);
        $container->setParameter('kernel.active_plugins', [
            'NonExistentClass\\That\\DoesNotExist' => [
                'name' => 'MyPlugin',
                'path' => '/my-plugin',
                'class' => 'NonExistentClass\\That\\DoesNotExist',
            ],
        ]);

        $this->expectExceptionObject(DependencyInjectionException::parameterHasWrongType(
            'kernel.active_plugins',
            'array<class-string, array>',
            'entry key "NonExistentClass\\That\\DoesNotExist" is not a valid class'
        ));

        $this->pass->process($container);
    }

    #[TestDox('throws when a plugin entry is missing required metadata fields')]
    public function testThrowsWhenPluginMetadataIsMissingFields(): void
    {
        $container = $this->buildContainer('prod');
        $container->setParameter('kernel.bundles_metadata', []);
        $container->setParameter('kernel.active_plugins', [
            FixturePlugin::class => [
                'name' => 'FixturePlugin',
                // Missing 'path' and 'class' fields
            ],
        ]);

        $this->expectExceptionObject(DependencyInjectionException::parameterHasWrongType(
            'kernel.active_plugins',
            'array{name: string, path: string, class: string}',
            \sprintf('entry for "%s" has missing or invalid metadata', FixturePlugin::class)
        ));

        $this->pass->process($container);
    }

    private function buildContainer(string $environment): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', $environment);

        $registryDef = new Definition(ContentElementTypeRegistry::class);
        $container->setDefinition(ContentElementTypeRegistry::class, $registryDef);

        return $container;
    }

    /**
     * Extracts the type name (first argument) from each inline ContentElementTypeSpecification Definition.
     *
     * @return list<string>
     */
    private function extractTypeNames(mixed $definitions): array
    {
        return array_keys($this->indexDefinitionsByName($definitions));
    }

    /**
     * @return array<string, Definition>
     */
    private function indexDefinitionsByName(mixed $definitions): array
    {
        static::assertIsArray($definitions);

        $indexed = [];
        foreach ($definitions as $def) {
            static::assertInstanceOf(Definition::class, $def);
            $name = $def->getArguments()[0] ?? null;
            static::assertIsString($name);
            $indexed[$name] = $def;
        }

        return $indexed;
    }

    private function loadFixtureDefinition(): Definition
    {
        $container = $this->buildContainer('prod');
        $container->setParameter('kernel.bundles_metadata', [
            'BundleA' => ['path' => self::FIXTURES_DIR . '/bundle-a'],
        ]);
        $container->setParameter('kernel.active_plugins', []);

        $registryDef = $container->getDefinition(ContentElementTypeRegistry::class);
        $this->pass->process($container);

        $byName = $this->indexDefinitionsByName($registryDef->getArgument(0));
        static::assertArrayHasKey('Sw:Test:Element', $byName);

        return $byName['Sw:Test:Element'];
    }
}

/**
 * @internal
 */
class FixturePlugin extends Plugin
{
}

/**
 * @internal
 */
class FixturePluginWithCustomTypeDir extends Plugin
{
    public static function getContentTypeDirectory(): string
    {
        return 'custom-types';
    }
}
