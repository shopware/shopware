<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Preset\Registry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\LayoutPresetPayloadCompiler;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Loader\AbstractContentSystemLayoutPresetLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Registry\ContentSystemLayoutPresetRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Specification\ContentSystemLayoutPresetSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Specification\Dto\LayoutPresetSpecificationDto;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Validation\ContentSystemLayoutPresetValidator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentSystemLayoutPresetRegistry::class)]
#[CoversClass(ContentSystemLayoutPresetSpecification::class)]
class ContentSystemLayoutPresetRegistryTest extends TestCase
{
    #[TestDox('aggregates presets from multiple loaders keyed by id')]
    public function testAllAggregatesFromMultipleLoaders(): void
    {
        $loaderA = static::createStub(AbstractContentSystemLayoutPresetLoader::class);
        $loaderA->method('load')->willReturn(['core.text-block' => $this->preset()]);

        $loaderB = static::createStub(AbstractContentSystemLayoutPresetLoader::class);
        $loaderB->method('load')->willReturn(['app.hero' => $this->preset()]);

        $registry = $this->registry([$loaderA, $loaderB]);

        $all = $registry->all();
        static::assertCount(2, $all);
        static::assertArrayHasKey('core.text-block', $all);
        static::assertArrayHasKey('app.hero', $all);
    }

    public function testPresetSnippetReferencesReachTheApiRepresentation(): void
    {
        $dto = new LayoutPresetSpecificationDto(
            name: 'Text',
            description: 'Text preset.',
            icon: 'regular-star',
            layout: [],
            nameSnippet: 'plugin.preset.name',
            descriptionSnippet: 'plugin.preset.description',
        );
        $loader = static::createStub(AbstractContentSystemLayoutPresetLoader::class);
        $loader->method('load')->willReturn(['plugin.text' => $dto]);

        $schema = $this->registry([$loader])->get('plugin.text')->toArray();

        static::assertSame('Text', $schema['name']);
        static::assertSame('plugin.preset.name', $schema['nameSnippet']);
        static::assertSame('plugin.preset.description', $schema['descriptionSnippet']);
    }

    #[TestDox('returns an empty array when no loaders are registered')]
    public function testAllReturnsEmptyArrayWithNoLoaders(): void
    {
        static::assertSame([], $this->registry([])->all());
    }

    #[TestDox('returns true for a known id and false for an unknown one')]
    public function testHas(): void
    {
        $loader = static::createStub(AbstractContentSystemLayoutPresetLoader::class);
        $loader->method('load')->willReturn(['core.text-block' => $this->preset()]);

        $registry = $this->registry([$loader]);

        static::assertTrue($registry->has('core.text-block'));
        static::assertFalse($registry->has('core.unknown'));
    }

    #[TestDox('returns the preset for a known id')]
    public function testGetReturnsPreset(): void
    {
        $loader = static::createStub(AbstractContentSystemLayoutPresetLoader::class);
        $loader->method('load')->willReturn(['core.text-block' => $this->preset('Text block')]);

        $preset = $this->registry([$loader])->get('core.text-block');

        static::assertSame('core.text-block', $preset->id);
        static::assertSame('Text block', $preset->name);
    }

    #[TestDox('throws for an unknown id on get')]
    public function testGetThrowsForUnknownId(): void
    {
        $this->expectExceptionObject(ContentSystemException::layoutPresetNotFound('core.unknown'));
        $this->registry([])->get('core.unknown');
    }

    #[TestDox('throws DecorationPatternException when calling getDecorated')]
    public function testGetDecoratedThrows(): void
    {
        $this->expectExceptionObject(new DecorationPatternException(ContentSystemLayoutPresetRegistry::class));
        $this->registry([])->getDecorated();
    }

    #[TestDox('throws when two loaders provide the same preset id')]
    public function testCrossLoaderDuplicateThrows(): void
    {
        $loaderA = static::createStub(AbstractContentSystemLayoutPresetLoader::class);
        $loaderA->method('load')->willReturn(['core.dupe' => $this->preset()]);

        $loaderB = static::createStub(AbstractContentSystemLayoutPresetLoader::class);
        $loaderB->method('load')->willReturn(['core.dupe' => $this->preset()]);

        $registry = $this->registry([$loaderA, $loaderB]);

        $this->expectExceptionObject(ContentSystemException::layoutPresetDuplicate('core.dupe'));
        $registry->all();
    }

    #[TestDox('compiles each preset against the presets of every loader, so references resolve across sources')]
    public function testCompilesAgainstThePresetsOfAllLoaders(): void
    {
        $core = $this->preset('Core', [['component' => 'Sw:Content:Text']]);
        $app = $this->preset('App', [['preset' => 'Sw:Core']]);

        $loaderA = static::createStub(AbstractContentSystemLayoutPresetLoader::class);
        $loaderA->method('load')->willReturn(['Sw:Core' => $core]);

        $loaderB = static::createStub(AbstractContentSystemLayoutPresetLoader::class);
        $loaderB->method('load')->willReturn(['MyApp:Hero' => $app]);

        $compiler = $this->createMock(LayoutPresetPayloadCompiler::class);
        $compiler->expects($this->exactly(2))
            ->method('compile')
            ->willReturnCallback(static function (array $layout, array $presets) use ($core, $app): array {
                static::assertSame(['Sw:Core' => $core, 'MyApp:Hero' => $app], $presets);

                return [['compiled' => $layout]];
            });

        $all = (new ContentSystemLayoutPresetRegistry([$loaderA, $loaderB], $compiler, new ContentSystemLayoutPresetValidator(), new NullLogger()))->all();

        static::assertSame([['compiled' => [['preset' => 'Sw:Core']]]], $all['MyApp:Hero']->payload);
    }

    #[TestDox('omits and logs presets with an unresolvable reference while compiling the others')]
    public function testUnresolvablePresetsAreOmittedAndLogged(): void
    {
        $loader = static::createStub(AbstractContentSystemLayoutPresetLoader::class);
        $loader->method('load')->willReturn([
            'App:Loop' => $this->preset('Loop', [['preset' => 'App:Loop']]),
            'App:Dangling' => $this->preset('Dangling', [['preset' => 'App:Missing']]),
            'Sw:Valid' => $this->preset('Valid', [['component' => 'Sw:Content:Text']]),
        ]);

        $compiler = $this->createMock(LayoutPresetPayloadCompiler::class);
        $compiler->expects($this->once())->method('compile')->with([['component' => 'Sw:Content:Text']])->willReturn([]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(2))->method('warning');

        $all = (new ContentSystemLayoutPresetRegistry([$loader], $compiler, new ContentSystemLayoutPresetValidator(), $logger))->all();

        static::assertSame(['Sw:Valid'], array_keys($all));
    }

    #[TestDox('carries category, priority and illustration of the preset into the specification')]
    public function testCarriesCategoryPriorityAndIllustration(): void
    {
        $loader = static::createStub(AbstractContentSystemLayoutPresetLoader::class);
        $loader->method('load')->willReturn([
            'Sw:TextBlock' => new LayoutPresetSpecificationDto('Text block', 'A preset.', 'regular-box', [], 'content', 100, 'preset-media-and-text'),
        ]);

        $preset = $this->registry([$loader])->get('Sw:TextBlock');

        static::assertSame('content', $preset->category);
        static::assertSame(100, $preset->priority);
        static::assertSame('preset-media-and-text', $preset->illustration);
        static::assertSame('content', $preset->toArray()['category']);
        static::assertSame(100, $preset->toArray()['priority']);
        static::assertSame('preset-media-and-text', $preset->toArray()['illustration']);
    }

    /**
     * @param iterable<AbstractContentSystemLayoutPresetLoader> $loaders
     */
    private function registry(iterable $loaders): ContentSystemLayoutPresetRegistry
    {
        return new ContentSystemLayoutPresetRegistry($loaders, static::createStub(LayoutPresetPayloadCompiler::class), new ContentSystemLayoutPresetValidator(), new NullLogger());
    }

    /**
     * @param list<array<string, mixed>> $layout
     */
    private function preset(string $name = 'Name', array $layout = []): LayoutPresetSpecificationDto
    {
        return new LayoutPresetSpecificationDto($name, 'A preset.', 'regular-box', $layout);
    }
}
