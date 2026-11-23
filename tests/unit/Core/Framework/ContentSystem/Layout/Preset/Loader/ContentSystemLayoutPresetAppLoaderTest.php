<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Preset\Loader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Loader\ContentSystemLayoutPresetAppLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Loader\DatabaseLayoutPresetLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Loader\YamlLayoutPresetLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Specification\Dto\LayoutPresetSpecificationDto;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Validation\ContentSystemLayoutPresetValidator;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentSystemLayoutPresetAppLoader::class)]
class ContentSystemLayoutPresetAppLoaderTest extends TestCase
{
    #[TestDox('returns the app presets when they reference core presets, installed app presets and their own presets')]
    public function testReturnsPresetsWithResolvableReferences(): void
    {
        $presets = [
            'TestApp:Page' => $this->preset([['preset' => 'Sw:ProductReviews'], ['preset' => 'OtherApp:Banner'], ['preset' => 'TestApp:Block']]),
            'TestApp:Block' => $this->preset([['component' => 'Sw:Content:Text']]),
        ];

        $loaded = $this->loader(
            $presets,
            ['Sw:ProductReviews' => $this->preset([['component' => 'Sw:Content:Text']])],
            ['OtherApp:Banner' => $this->preset([['component' => 'Sw:Content:Text']])],
        )->load('/app/presets', 'TestApp');

        static::assertSame($presets, $loaded);
    }

    #[TestDox('rejects an unknown reference, such as a typo in a core preset id')]
    public function testRejectsUnknownReference(): void
    {
        $loader = $this->loader(
            ['TestApp:Hero' => $this->preset([['preset' => 'Sw:ProductReviewz']])],
            ['Sw:ProductReviews' => $this->preset([['component' => 'Sw:Content:Text']])],
        );

        try {
            $loader->load('/app/presets', 'TestApp');
            static::fail('Expected the unknown reference to be rejected.');
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::LAYOUT_PRESETS_INVALID, $exception->getErrorCode());
            static::assertStringContainsString('Layout preset "TestApp:Hero" references unknown preset "Sw:ProductReviewz" via TestApp:Hero -> Sw:ProductReviewz', $exception->getMessage());
        }
    }

    #[TestDox('rejects two app presets that reference each other')]
    public function testRejectsCircularReference(): void
    {
        $loader = $this->loader([
            'TestApp:A' => $this->preset([['preset' => 'TestApp:B']]),
            'TestApp:B' => $this->preset([['preset' => 'TestApp:A']]),
        ]);

        try {
            $loader->load('/app/presets', 'TestApp');
            static::fail('Expected the circular reference to be rejected.');
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::LAYOUT_PRESETS_INVALID, $exception->getErrorCode());
            static::assertStringContainsString('Layout preset "TestApp:A" has a circular reference: TestApp:A -> TestApp:B -> TestApp:A', $exception->getMessage());
            static::assertStringContainsString('Layout preset "TestApp:B" has a circular reference: TestApp:B -> TestApp:A -> TestApp:B', $exception->getMessage());
        }
    }

    #[TestDox('does not resolve references against the previously installed presets of the app itself')]
    public function testIgnoresTheAppsOwnInstalledPresets(): void
    {
        $loader = $this->loader(
            ['TestApp:Page' => $this->preset([['preset' => 'TestApp:Removed']])],
            installed: ['TestApp:Removed' => $this->preset([['component' => 'Sw:Content:Text']])],
        );

        try {
            $loader->load('/app/presets', 'TestApp');
            static::fail('Expected the reference to the removed preset to be rejected.');
        } catch (ContentSystemException $exception) {
            static::assertStringContainsString('references unknown preset "TestApp:Removed"', $exception->getMessage());
        }
    }

    #[TestDox('propagates a load failure of the app presets directory')]
    public function testPropagatesLoadFailure(): void
    {
        $failure = ContentSystemException::layoutPresetLoadFailed('broken.yaml', 'Invalid YAML syntax');

        $yamlLoader = static::createStub(YamlLayoutPresetLoader::class);
        $yamlLoader->method('loadDtosFromDirectory')->willThrowException($failure);

        $loader = new ContentSystemLayoutPresetAppLoader($yamlLoader, static::createStub(DatabaseLayoutPresetLoader::class), new ContentSystemLayoutPresetValidator());

        $this->expectExceptionObject($failure);

        $loader->load('/app/presets', 'TestApp');
    }

    /**
     * @param array<string, LayoutPresetSpecificationDto> $presets
     * @param array<string, LayoutPresetSpecificationDto> $yaml
     * @param array<string, LayoutPresetSpecificationDto> $installed
     */
    private function loader(array $presets, array $yaml = [], array $installed = []): ContentSystemLayoutPresetAppLoader
    {
        $yamlLoader = static::createStub(YamlLayoutPresetLoader::class);
        $yamlLoader->method('loadDtosFromDirectory')->willReturn($presets);
        $yamlLoader->method('load')->willReturn($yaml);

        $databaseLoader = static::createStub(DatabaseLayoutPresetLoader::class);
        $databaseLoader->method('load')->willReturn($installed);

        return new ContentSystemLayoutPresetAppLoader($yamlLoader, $databaseLoader, new ContentSystemLayoutPresetValidator());
    }

    /**
     * @param list<array<string, mixed>> $layout
     */
    private function preset(array $layout): LayoutPresetSpecificationDto
    {
        return new LayoutPresetSpecificationDto('Preset', 'A preset.', 'regular-box', $layout);
    }
}
