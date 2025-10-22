<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Theme\ThemeRuntimeConfig;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ThemeRuntimeConfig::class)]
class ThemeRuntimeConfigTest extends TestCase
{
    public function testFromArray(): void
    {
        $data = [
            'themeId' => '12345',
            'technicalName' => 'testTheme',
            'resolvedConfig' => ['key' => 'value'],
            'viewInheritance' => ['parentTheme'],
            'scriptFiles' => ['file1.js', 'file2.js'],
            'iconSets' => ['iconSet1' => ['path' => 'path/to/iconSet1', 'namespace' => 'testTheme']],
            'updatedAt' => new \DateTimeImmutable(),
        ];

        $config = ThemeRuntimeConfig::fromArray($data);

        static::assertSame($data['themeId'], $config->themeId);
        static::assertSame($data['technicalName'], $config->technicalName);
        static::assertSame($data['resolvedConfig'], $config->resolvedConfig);
        static::assertSame($data['viewInheritance'], $config->viewInheritance);
        static::assertSame($data['scriptFiles'], $config->scriptFiles);
        static::assertSame($data['iconSets'], $config->iconSets);
        static::assertEquals($data['updatedAt'], $config->updatedAt);
    }

    public function testFromArrayWithDefaults(): void
    {
        $data = [
            'themeId' => '12345',
            'technicalName' => 'testTheme',
        ];

        $before = new \DateTimeImmutable();
        $config = ThemeRuntimeConfig::fromArray($data);
        $after = new \DateTimeImmutable();

        static::assertSame($data['themeId'], $config->themeId);
        static::assertSame($data['technicalName'], $config->technicalName);
        static::assertSame([], $config->resolvedConfig);
        static::assertSame([], $config->viewInheritance);
        static::assertNull($config->scriptFiles);
        static::assertSame([], $config->iconSets);

        static::assertGreaterThanOrEqual($before, $config->updatedAt);
        static::assertLessThanOrEqual($after, $config->updatedAt);
    }

    public function testWithMethodWithRequiredFields(): void
    {
        $originalConfig = $this->getTestConfig();

        $newConfig = $originalConfig->with([
            'themeId' => '67890',
            'technicalName' => 'newTheme',
        ]);

        static::assertSame('67890', $newConfig->themeId);
        static::assertSame('newTheme', $newConfig->technicalName);
        static::assertSame($originalConfig->resolvedConfig, $newConfig->resolvedConfig);
        static::assertSame($originalConfig->viewInheritance, $newConfig->viewInheritance);
        static::assertSame($originalConfig->scriptFiles, $newConfig->scriptFiles);
        static::assertSame($originalConfig->iconSets, $newConfig->iconSets);
        static::assertSame($originalConfig->updatedAt, $newConfig->updatedAt);
    }

    public function testWithMethodWithAllFields(): void
    {
        $originalConfig = $this->getTestConfig();

        $newUpdatedAt = new \DateTimeImmutable('2024-01-01');
        $newConfig = $originalConfig->with([
            'themeId' => '67890',
            'technicalName' => 'newTheme',
            'resolvedConfig' => ['newKey' => 'newValue'],
            'viewInheritance' => ['newParentTheme'],
            'scriptFiles' => ['newFile.js'],
            'iconSets' => ['newIconSet' => ['path' => 'path/to/newIconSet', 'namespace' => 'newTheme']],
            'updatedAt' => $newUpdatedAt,
        ]);

        static::assertSame('67890', $newConfig->themeId);
        static::assertSame('newTheme', $newConfig->technicalName);
        static::assertSame(['newKey' => 'newValue'], $newConfig->resolvedConfig);
        static::assertSame(['newParentTheme'], $newConfig->viewInheritance);
        static::assertSame(['newFile.js'], $newConfig->scriptFiles);
        static::assertSame(['newIconSet' => ['path' => 'path/to/newIconSet', 'namespace' => 'newTheme']], $newConfig->iconSets);
        static::assertSame($newUpdatedAt, $newConfig->updatedAt);
    }

    private function getTestConfig(): ThemeRuntimeConfig
    {
        return ThemeRuntimeConfig::fromArray([
            'themeId' => '12345',
            'technicalName' => 'testTheme',
            'resolvedConfig' => ['key' => 'value'],
            'viewInheritance' => ['parentTheme'],
            'scriptFiles' => ['file1.js', 'file2.js'],
            'iconSets' => ['iconSet1' => ['path' => 'path/to/iconSet1', 'namespace' => 'testTheme']],
            'updatedAt' => new \DateTimeImmutable(),
        ]);
    }
}
