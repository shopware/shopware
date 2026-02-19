<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Hydration\DataLoader\NavigationLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\NavigationLoader\NavigationLoaderConfig;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\NavigationLoader\NavigationLoaderConfigSerializer;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @internal
 */
#[CoversClass(NavigationLoaderConfigSerializer::class)]
class NavigationLoaderConfigSerializerTest extends TestCase
{
    private NavigationLoaderConfigSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new NavigationLoaderConfigSerializer();
    }

    #[TestDox('returns navigation source identifier')]
    public function testGetSourceReturnsNavigationString(): void
    {
        static::assertSame('navigation', NavigationLoaderConfigSerializer::getSource());
    }

    #[TestDox('decodes empty array into NavigationLoaderConfig with default values')]
    public function testDecodeEmptyArrayReturnsNavigationLoaderConfigWithDefaults(): void
    {
        $result = $this->serializer->decode([]);

        static::assertInstanceOf(NavigationLoaderConfig::class, $result);
        static::assertNull($result->rootId);
        static::assertSame(NavigationLoaderConfig::DEFAULT_DEPTH, $result->depth);
        static::assertSame('activeId', $result->activeProperty);
    }

    #[TestDox('decodes config with all fields provided into NavigationLoaderConfig with all values')]
    public function testDecodeWithAllFieldsReturnsNavigationLoaderConfigWithAllValues(): void
    {
        $result = $this->serializer->decode([
            'rootId' => 'main-navigation',
            'depth' => 3,
            'activeProperty' => 'categoryId',
        ]);

        static::assertInstanceOf(NavigationLoaderConfig::class, $result);
        static::assertSame('main-navigation', $result->rootId);
        static::assertSame(3, $result->depth);
        static::assertSame('categoryId', $result->activeProperty);
    }

    #[TestDox('decodes config with only rootId into NavigationLoaderConfig with rootId and defaults')]
    public function testDecodeWithRootIdOnlyReturnsNavigationLoaderConfigWithRootId(): void
    {
        $result = $this->serializer->decode(['rootId' => 'service-navigation']);

        static::assertInstanceOf(NavigationLoaderConfig::class, $result);
        static::assertSame('service-navigation', $result->rootId);
        static::assertSame(NavigationLoaderConfig::DEFAULT_DEPTH, $result->depth);
        static::assertSame('activeId', $result->activeProperty);
    }

    #[TestDox('decodes config with only depth into NavigationLoaderConfig with depth and defaults')]
    public function testDecodeWithDepthOnlyReturnsNavigationLoaderConfigWithDepth(): void
    {
        $result = $this->serializer->decode(['depth' => 5]);

        static::assertInstanceOf(NavigationLoaderConfig::class, $result);
        static::assertNull($result->rootId);
        static::assertSame(5, $result->depth);
        static::assertSame('activeId', $result->activeProperty);
    }

    #[TestDox('decodes config with only activeProperty into NavigationLoaderConfig with activeProperty and defaults')]
    public function testDecodeWithActivePropertyOnlyReturnsNavigationLoaderConfigWithActiveProperty(): void
    {
        $result = $this->serializer->decode(['activeProperty' => 'navActiveId']);

        static::assertInstanceOf(NavigationLoaderConfig::class, $result);
        static::assertNull($result->rootId);
        static::assertSame(NavigationLoaderConfig::DEFAULT_DEPTH, $result->depth);
        static::assertSame('navActiveId', $result->activeProperty);
    }

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('invalidRootIdProvider')]
    #[TestDox('throws exception when rootId is invalid')]
    public function testDecodeWithInvalidRootIdThrowsException(array $data): void
    {
        $this->expectException(ContentSystemException::class);
        $this->expectExceptionMessage('Field rootId expected non-empty string');

        $this->serializer->decode($data);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function invalidRootIdProvider(): array
    {
        return [
            'rootId is non-string type' => [['rootId' => 42]],
            'rootId is empty string' => [['rootId' => '']],
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('invalidDepthProvider')]
    #[TestDox('throws exception when depth is invalid')]
    public function testDecodeWithInvalidDepthThrowsException(array $data): void
    {
        $this->expectException(ContentSystemException::class);
        $this->expectExceptionMessage('Field depth expected positive int');

        $this->serializer->decode($data);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function invalidDepthProvider(): array
    {
        return [
            'depth is zero (boundary)' => [['depth' => 0]],
            'depth is negative' => [['depth' => -1]],
            'depth is non-int type' => [['depth' => '3']],
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('invalidActivePropertyProvider')]
    #[TestDox('throws exception when activeProperty is invalid')]
    public function testDecodeWithInvalidActivePropertyThrowsException(array $data): void
    {
        $this->expectException(ContentSystemException::class);
        $this->expectExceptionMessage('Field activeProperty expected non-empty string');

        $this->serializer->decode($data);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function invalidActivePropertyProvider(): array
    {
        return [
            'activeProperty is non-string type' => [['activeProperty' => 42]],
            'activeProperty is empty string' => [['activeProperty' => '']],
        ];
    }

    #[TestDox('encodes NavigationLoaderConfig with all defaults into empty array')]
    public function testEncodeConfigWithDefaultsReturnsEmptyArray(): void
    {
        $config = new NavigationLoaderConfig();

        $result = $this->serializer->encode($config);

        static::assertSame([], $result);
    }

    #[TestDox('encodes NavigationLoaderConfig with rootId into array with rootId')]
    public function testEncodeConfigWithRootIdIncludesRootIdKey(): void
    {
        $config = new NavigationLoaderConfig(rootId: 'main-navigation');

        $result = $this->serializer->encode($config);

        static::assertSame(['rootId' => 'main-navigation'], $result);
        static::assertArrayNotHasKey('depth', $result);
        static::assertArrayNotHasKey('activeProperty', $result);
    }

    #[TestDox('encodes NavigationLoaderConfig with non-default depth into array with depth')]
    public function testEncodeConfigWithNonDefaultDepthIncludesDepthKey(): void
    {
        $config = new NavigationLoaderConfig(depth: 5);

        $result = $this->serializer->encode($config);

        static::assertSame(['depth' => 5], $result);
        static::assertArrayNotHasKey('rootId', $result);
        static::assertArrayNotHasKey('activeProperty', $result);
    }

    #[TestDox('encodes NavigationLoaderConfig with non-default activeProperty into array with activeProperty')]
    public function testEncodeConfigWithNonDefaultActivePropertyIncludesActivePropertyKey(): void
    {
        $config = new NavigationLoaderConfig(activeProperty: 'categoryId');

        $result = $this->serializer->encode($config);

        static::assertSame(['activeProperty' => 'categoryId'], $result);
        static::assertArrayNotHasKey('rootId', $result);
        static::assertArrayNotHasKey('depth', $result);
    }

    #[TestDox('encodes NavigationLoaderConfig with all non-default values into full array')]
    public function testEncodeConfigWithAllNonDefaultValuesReturnsFullArray(): void
    {
        $config = new NavigationLoaderConfig(
            rootId: 'footer-navigation',
            depth: 3,
            activeProperty: 'navCategoryId',
        );

        $result = $this->serializer->encode($config);

        static::assertSame([
            'rootId' => 'footer-navigation',
            'depth' => 3,
            'activeProperty' => 'navCategoryId',
        ], $result);
    }

    #[TestDox('throws exception when encoding a non-NavigationLoaderConfig config instance')]
    public function testEncodeWithWrongConfigTypeThrowsException(): void
    {
        $wrongConfig = new class extends AbstractContentDataLoaderConfig {
            public function getDecorated(): AbstractContentDataLoaderConfig
            {
                throw new DecorationPatternException(self::class);
            }
        };

        $this->expectException(ContentSystemException::class);
        $this->expectExceptionMessage('Field config expected');

        $this->serializer->encode($wrongConfig);
    }

    #[TestDox('round-trips a full config without data loss')]
    public function testDecodeAndEncodeAreInverseForFullConfig(): void
    {
        $original = [
            'rootId' => 'main-navigation',
            'depth' => 4,
            'activeProperty' => 'navCategoryId',
        ];

        $config = $this->serializer->decode($original);
        $encoded = $this->serializer->encode($config);

        static::assertSame($original, $encoded);
    }

    #[TestDox('round-trips an empty config without data loss')]
    public function testDecodeAndEncodeAreInverseForEmptyConfig(): void
    {
        $original = [];

        $config = $this->serializer->decode($original);
        $encoded = $this->serializer->encode($config);

        static::assertSame($original, $encoded);
    }

    #[TestDox('round-trips a config with only rootId without data loss')]
    public function testDecodeAndEncodeAreInverseForConfigWithRootIdOnly(): void
    {
        $original = ['rootId' => 'service-navigation'];

        $config = $this->serializer->decode($original);
        $encoded = $this->serializer->encode($config);

        static::assertSame($original, $encoded);
    }

    #[TestDox('throws DecorationPatternException when getDecorated is called')]
    public function testGetDecoratedThrowsDecorationPatternException(): void
    {
        $this->expectException(DecorationPatternException::class);
        $this->expectExceptionMessage('The getDecorated() function of core class');

        $this->serializer->getDecorated();
    }
}
