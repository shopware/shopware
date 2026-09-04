<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Type\Loader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\ElementTypeNameResolver;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\ElementTypeSourceDirectory;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\YamlTypeLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Serialization\ElementTypeSpecificationSerializer;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(YamlTypeLoader::class)]
class YamlTypeLoaderTest extends TestCase
{
    private const BUNDLE_A_TYPES_DIR = __DIR__ . '/fixtures/bundle-a/Resources/content-system/types';

    private const PLUGIN_TYPES_DIR = __DIR__ . '/fixtures/test-plugin/Resources/content-system/types';

    private const MINIMAL_VALID_YAML = "meta:\n  label: \"Test\"\n  description: \"Test.\"\n";

    private string $tempDir = '';

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/yaml-type-loader-test-' . getmypid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->tempDir);
    }

    #[TestDox('returns specifications from all configured directories with their source labels')]
    public function testLoadsSpecificationsFromInjectedDirectories(): void
    {
        $loader = $this->createLoader([
            new ElementTypeSourceDirectory('bundle-a', self::BUNDLE_A_TYPES_DIR, 'Sw'),
            new ElementTypeSourceDirectory('test-plugin', self::PLUGIN_TYPES_DIR, 'TestPlugin'),
        ]);

        $definitions = $loader->load();

        static::assertCount(2, $definitions);

        $byName = [];
        foreach ($definitions as $spec) {
            $byName[$spec->name()] = $spec;
        }

        static::assertArrayHasKey('Sw:Test:Element', $byName);
        static::assertArrayHasKey('TestPlugin:Plugin:Element', $byName);
    }

    #[TestDox('returns resolved DTOs with correct name and source when loading from a single directory')]
    public function testLoadsDtosFromSingleDirectory(): void
    {
        $loader = $this->createLoader([]);

        $dtos = $loader->loadDtosFromDirectory(self::BUNDLE_A_TYPES_DIR, 'test-source', 'Sw');

        static::assertCount(1, $dtos);
        static::assertSame('Sw:Test:Element', $dtos[0]->name);
        static::assertSame('test-source', $dtos[0]->source);
    }

    #[TestDox('returns specifications with correct name and source when loading from a single directory')]
    public function testLoadsSpecificationsFromSingleDirectory(): void
    {
        $loader = $this->createLoader([]);

        $definitions = $loader->loadFromDirectory(self::BUNDLE_A_TYPES_DIR, 'test-source', 'Sw');

        static::assertCount(1, $definitions);
        static::assertSame('Sw:Test:Element', $definitions[0]->name());
        static::assertSame('test-source', $definitions[0]->source());
    }

    #[TestDox('defines grid container spacing as breakpoint-aware element properties')]
    public function testGridContainerDefinesBreakpointAwareSpacingProperties(): void
    {
        $loaderDirectory = \dirname((string) (new \ReflectionClass(YamlTypeLoader::class))->getFileName());
        $definitions = $this->createLoader([
            new ElementTypeSourceDirectory('core', $loaderDirectory . '/../Definitions', 'Sw'),
        ])->load();

        $byName = [];
        foreach ($definitions as $definition) {
            $byName[$definition->name()] = $definition;
        }

        $containerProperties = $byName['Sw:Grid:Container']->toSchema()['properties'];

        foreach (['padding', 'margin'] as $spacingProperty) {
            static::assertSame(['string', 'object'], $containerProperties[$spacingProperty]['type']);
            $adminUi = self::adminUi($containerProperties[$spacingProperty]);
            static::assertSame('box-spacing', $adminUi['component']);
            static::assertTrue($adminUi['breakpointAware']);
            static::assertIsArray($containerProperties[$spacingProperty]['properties']);
            static::assertSame(
                ['xs', 'sm', 'md', 'lg', 'xl', 'xxl'],
                array_keys($containerProperties[$spacingProperty]['properties']),
            );
        }

        static::assertIsArray($containerProperties['padding']['properties']);
        foreach ($containerProperties['padding']['properties'] as $breakpoint) {
            static::assertSame('0 20px 0 20px', $breakpoint['default']);
        }

        static::assertIsArray($containerProperties['margin']['properties']);
        foreach ($containerProperties['margin']['properties'] as $breakpoint) {
            static::assertSame('0 0 24px 0', $breakpoint['default']);
        }

        $expectedPanels = [
            'mode' => 'general',
            'itemMinWidth' => 'general',
            'columns' => 'general',
            'rows' => 'general',
            'centered' => 'general',
            'gap' => 'spacing',
            'padding' => 'spacing',
            'margin' => 'spacing',
            'align' => 'alignment',
            'alignContent' => 'alignment',
            'justify' => 'alignment',
            'justifyContent' => 'alignment',
            'border' => 'border',
            'borderVariant' => 'border',
            'borderRadius' => 'border',
            'backgroundOpacity' => 'background',
            'shadowOffsetX' => 'shadow',
            'shadowOffsetY' => 'shadow',
            'shadowSpread' => 'shadow',
            'shadowBlur' => 'shadow',
            'shadowOpacity' => 'shadow',
            'shadowInset' => 'shadow',
            'shadowColor' => 'shadow',
            'backgroundColor' => 'background',
            'backgroundImage' => 'background',
            'backgroundImageMode' => 'background',
        ];

        foreach ($expectedPanels as $property => $panel) {
            static::assertSame($panel, self::adminUi($containerProperties[$property])['panel']);
        }

        $backgroundOpacityUi = self::adminUi($containerProperties['backgroundOpacity']);
        static::assertSame('slider', $backgroundOpacityUi['component']);
        static::assertIsArray($backgroundOpacityUi['props']);
        static::assertSame([0, 100, 1], array_values($backgroundOpacityUi['props']));
        static::assertSame('auto', $containerProperties['backgroundImageMode']['default']);
        static::assertSame(['auto', 'cover', 'contain'], $containerProperties['backgroundImageMode']['enum']);
        static::assertSame('select', self::adminUi($containerProperties['backgroundImageMode'])['component']);
        static::assertSame(['0', '4px', '8px', '16px', '50%'], $containerProperties['borderRadius']['enum']);
        static::assertSame('radio-panel', self::adminUi($containerProperties['borderRadius'])['component']);

        foreach (['shadowOffsetX', 'shadowOffsetY', 'shadowSpread', 'shadowBlur', 'shadowOpacity'] as $shadowProperty) {
            static::assertSame('slider', self::adminUi($containerProperties[$shadowProperty])['component']);
        }

        static::assertSame('color', self::adminUi($containerProperties['shadowColor'])['component']);
        static::assertFalse($containerProperties['shadowInset']['default']);
    }

    #[TestDox('returns the directory\'s specifications keyed by their resolved type name, the overlay shape')]
    public function testLoadsOverlayKeyedByResolvedTypeName(): void
    {
        file_put_contents($this->tempDir . '/button.yaml', self::MINIMAL_VALID_YAML);
        file_put_contents($this->tempDir . '/card.yaml', self::MINIMAL_VALID_YAML);

        $overlay = $this->createLoader([])->loadOverlayFromDirectory($this->tempDir, 'test-source', 'Sw');

        static::assertCount(2, $overlay);
        static::assertArrayHasKey('Sw:Button', $overlay);
        static::assertArrayHasKey('Sw:Card', $overlay);
        static::assertSame('Sw:Button', $overlay['Sw:Button']->name());
        static::assertSame('Sw:Card', $overlay['Sw:Card']->name());
    }

    #[TestDox('returns empty array when no directories are injected')]
    public function testReturnsEmptyArrayWhenNoDirectoriesInjected(): void
    {
        $loader = $this->createLoader([]);

        static::assertSame([], $loader->load());
    }

    #[TestDox('returns empty array for non-existent directory path')]
    public function testReturnsEmptyArrayForNonExistentDirectory(): void
    {
        $loader = $this->createLoader([
            new ElementTypeSourceDirectory('missing', '/path/that/does/not/exist', 'X'),
        ]);

        static::assertSame([], $loader->load());
    }

    #[TestDox('returns empty array for directory without matching files')]
    public function testReturnsEmptyArrayForDirectoryWithoutYamlFiles(): void
    {
        $loader = $this->createLoader([
            new ElementTypeSourceDirectory('empty', $this->tempDir, 'X'),
        ]);

        static::assertSame([], $loader->load());
    }

    #[TestDox('throws duplicate exception when the same type name exists in two different directories')]
    public function testThrowsForCrossDirectoryDuplicateTypeName(): void
    {
        $dirA = $this->tempDir . '/dir-a';
        $dirB = $this->tempDir . '/dir-b';
        mkdir($dirA, 0777, true);
        mkdir($dirB, 0777, true);

        file_put_contents($dirA . '/dup.yaml', self::MINIMAL_VALID_YAML);
        file_put_contents($dirB . '/dup.yaml', self::MINIMAL_VALID_YAML);

        $loader = $this->createLoader([
            new ElementTypeSourceDirectory('source-a', $dirA, 'Sw'),
            new ElementTypeSourceDirectory('source-b', $dirB, 'Sw'),
        ]);

        $this->expectExceptionObject(
            ContentSystemException::elementTypeDuplicate('Sw:Dup', 'source-a', 'source-b')
        );
        $loader->load();
    }

    #[TestDox('throws duplicate exception when yaml and yml files resolve to the same type name')]
    public function testThrowsForDuplicateTypeNamesInSameDirectory(): void
    {
        file_put_contents($this->tempDir . '/button.yaml', self::MINIMAL_VALID_YAML);
        file_put_contents($this->tempDir . '/button.yml', self::MINIMAL_VALID_YAML);

        $loader = $this->createLoader([
            new ElementTypeSourceDirectory('source', $this->tempDir, 'Sw'),
        ]);

        $this->expectExceptionObject(
            ContentSystemException::elementTypeDuplicate('Sw:Button', 'button.yaml', 'button.yml')
        );
        $loader->load();
    }

    #[TestDox('throws when file contains unparsable content')]
    public function testThrowsForUnparsableFileContent(): void
    {
        file_put_contents($this->tempDir . '/bad.yaml', "meta:\n  label: \"Bad\n  broken: [");

        $loader = $this->createLoader([
            new ElementTypeSourceDirectory('source', $this->tempDir, 'Sw'),
        ]);

        // Parse message varies by Symfony version, so assert file path + error prefix
        $this->expectException(ContentSystemException::class);
        $this->expectExceptionMessageMatches('/' . preg_quote($this->tempDir . '/bad.yaml', '/') . '/');
        $loader->load();
    }

    #[TestDox('throws when file content is scalar instead of a map')]
    public function testThrowsWhenFileContentIsScalar(): void
    {
        file_put_contents($this->tempDir . '/scalar.yaml', 'just a string');

        $loader = $this->createLoader([
            new ElementTypeSourceDirectory('source', $this->tempDir, 'Sw'),
        ]);

        $this->expectExceptionObject(
            ContentSystemException::elementTypeLoadFailed(
                $this->tempDir . '/scalar.yaml',
                'YAML file must contain an array/map, got string'
            )
        );
        $loader->load();
    }

    #[TestDox('throws batch validation exception when files have violations')]
    public function testThrowsForValidationViolations(): void
    {
        $yaml = "meta:\n  label: \"\"\n  description: \"\"";
        file_put_contents($this->tempDir . '/invalid.yaml', $yaml);

        $loader = $this->createLoader([
            new ElementTypeSourceDirectory('source', $this->tempDir, 'Sw'),
        ]);

        $this->expectException(ContentSystemException::class);
        $this->expectExceptionMessageMatches('/types\[Sw:Invalid\]\.label/');
        $loader->load();
    }

    #[TestDox('batch validation reports violations from multiple invalid files')]
    public function testBatchValidationReportsMultipleFiles(): void
    {
        $yaml = "meta:\n  label: \"\"\n  description: \"\"";
        mkdir($this->tempDir . '/a', 0777, true);
        mkdir($this->tempDir . '/b', 0777, true);
        file_put_contents($this->tempDir . '/a/invalid-a.yaml', $yaml);
        file_put_contents($this->tempDir . '/b/invalid-b.yaml', $yaml);

        $loader = $this->createLoader([
            new ElementTypeSourceDirectory('source', $this->tempDir, 'Sw'),
        ]);

        $this->expectException(ContentSystemException::class);
        $this->expectExceptionMessageMatches('/(?=.*types\[Sw:A:InvalidA\])(?=.*types\[Sw:B:InvalidB\])/s');
        $loader->load();
    }

    /**
     * @param array<string, mixed> $property
     *
     * @return array<mixed>
     */
    private static function adminUi(array $property): array
    {
        static::assertIsArray($property['adminUI']);

        return $property['adminUI'];
    }

    /**
     * @param list<ElementTypeSourceDirectory> $directories
     */
    private function createLoader(array $directories): YamlTypeLoader
    {
        return new YamlTypeLoader(
            new ElementTypeSpecificationSerializer(),
            Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator(),
            new ElementTypeNameResolver(),
            $directories,
        );
    }
}
