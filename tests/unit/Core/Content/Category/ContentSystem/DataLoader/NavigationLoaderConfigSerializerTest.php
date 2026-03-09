<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Category\ContentSystem\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWithJson;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryException;
use Shopware\Core\Content\Category\ContentSystem\DataLoader\NavigationLoaderConfig;
use Shopware\Core\Content\Category\ContentSystem\DataLoader\NavigationLoaderConfigSerializer;
use Shopware\Core\Test\Stub\ContentSystem\StubLoaderConfig;

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
    #[TestWithJson('[{"rootId": 42}, "integer"]', 'rootId is non-string type')]
    #[TestWithJson('[{"rootId": ""}, "string"]', 'rootId is empty string')]
    #[TestDox('throws exception when rootId is invalid')]
    public function testDecodeWithInvalidRootIdThrowsException(array $data, string $actualType): void
    {
        $this->expectExceptionObject(
            CategoryException::invalidFieldValueType('rootId', 'non-empty string', $actualType)
        );

        $this->serializer->decode($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    #[TestWithJson('[{"depth": 0}, "integer"]', 'depth is zero (boundary)')]
    #[TestWithJson('[{"depth": -1}, "integer"]', 'depth is negative')]
    #[TestWithJson('[{"depth": "3"}, "string"]', 'depth is non-int type')]
    #[TestDox('throws exception when depth is invalid')]
    public function testDecodeWithInvalidDepthThrowsException(array $data, string $actualType): void
    {
        $this->expectExceptionObject(
            CategoryException::invalidFieldValueType('depth', 'positive int', $actualType)
        );

        $this->serializer->decode($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    #[TestWithJson('[{"activeProperty": 42}, "integer"]', 'activeProperty is non-string type')]
    #[TestWithJson('[{"activeProperty": ""}, "string"]', 'activeProperty is empty string')]
    #[TestDox('throws exception when activeProperty is invalid')]
    public function testDecodeWithInvalidActivePropertyThrowsException(array $data, string $actualType): void
    {
        $this->expectExceptionObject(
            CategoryException::invalidFieldValueType('activeProperty', 'non-empty string', $actualType)
        );

        $this->serializer->decode($data);
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
    }

    #[TestDox('encodes NavigationLoaderConfig with non-default depth into array with depth')]
    public function testEncodeConfigWithNonDefaultDepthIncludesDepthKey(): void
    {
        $config = new NavigationLoaderConfig(depth: 5);

        $result = $this->serializer->encode($config);

        static::assertSame(['depth' => 5], $result);
    }

    #[TestDox('encodes NavigationLoaderConfig with non-default activeProperty into array with activeProperty')]
    public function testEncodeConfigWithNonDefaultActivePropertyIncludesActivePropertyKey(): void
    {
        $config = new NavigationLoaderConfig(activeProperty: 'categoryId');

        $result = $this->serializer->encode($config);

        static::assertSame(['activeProperty' => 'categoryId'], $result);
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
        $this->expectExceptionObject(
            CategoryException::invalidFieldValueType('config', NavigationLoaderConfig::class, StubLoaderConfig::class)
        );

        $this->serializer->encode(new StubLoaderConfig());
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
}
