<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Category\ContentSystem\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWithJson;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryException;
use Shopware\Core\Content\Category\ContentSystem\DataLoader\ServiceMenuLoaderConfig;
use Shopware\Core\Content\Category\ContentSystem\DataLoader\ServiceMenuLoaderConfigSerializer;
use Shopware\Core\Test\Stub\ContentSystem\StubLoaderConfig;

/**
 * @internal
 */
#[CoversClass(ServiceMenuLoaderConfigSerializer::class)]
class ServiceMenuLoaderConfigSerializerTest extends TestCase
{
    private ServiceMenuLoaderConfigSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new ServiceMenuLoaderConfigSerializer();
    }

    #[TestDox('returns service_menu source identifier')]
    public function testGetSourceReturnsServiceMenuString(): void
    {
        static::assertSame('service_menu', ServiceMenuLoaderConfigSerializer::getSource());
    }

    #[TestDox('decodes empty array into ServiceMenuLoaderConfig with null rootId')]
    public function testDecodeEmptyArrayReturnsConfigWithNullRootId(): void
    {
        $result = $this->serializer->decode([]);

        static::assertInstanceOf(ServiceMenuLoaderConfig::class, $result);
        static::assertNull($result->rootId);
    }

    #[TestDox('decodes config with valid rootId into config with rootId set')]
    public function testDecodeWithValidRootIdSetsRootId(): void
    {
        $result = $this->serializer->decode(['rootId' => 'abc-123']);

        static::assertInstanceOf(ServiceMenuLoaderConfig::class, $result);
        static::assertSame('abc-123', $result->rootId);
    }

    /**
     * @param array<string, mixed> $data
     */
    #[TestWithJson('[{"rootId": ""}, "string"]', 'rootId is empty string')]
    #[TestWithJson('[{"rootId": 42}, "integer"]', 'rootId is non-string type')]
    #[TestDox('throws exception when rootId is invalid')]
    public function testDecodeWithInvalidRootIdThrowsException(array $data, string $actualType): void
    {
        $this->expectExceptionObject(
            CategoryException::invalidFieldValueType('rootId', 'non-empty string', $actualType)
        );

        $this->serializer->decode($data);
    }

    #[TestDox('encodes config with defaults into empty array')]
    public function testEncodeConfigWithDefaultsReturnsEmptyArray(): void
    {
        $config = new ServiceMenuLoaderConfig();

        $result = $this->serializer->encode($config);

        static::assertSame([], $result);
    }

    #[TestDox('encodes config with rootId into array containing rootId key')]
    public function testEncodeConfigWithRootIdIncludesRootIdKey(): void
    {
        $config = new ServiceMenuLoaderConfig(rootId: 'my-root-id');

        $result = $this->serializer->encode($config);

        static::assertSame(['rootId' => 'my-root-id'], $result);
    }

    /**
     * @param array<string, mixed> $original
     */
    #[DataProvider('roundTripsProvider')]
    #[TestDox('round-trips $_dataName without data loss')]
    public function testDecodeAndEncodeAreInverse(array $original): void
    {
        $config = $this->serializer->decode($original);
        $encoded = $this->serializer->encode($config);

        static::assertSame($original, $encoded);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function roundTripsProvider(): iterable
    {
        yield 'empty config' => [[]];
        yield 'rootId only' => [['rootId' => 'service-navigation']];
    }

    #[TestDox('throws exception when encoding a non-ServiceMenuLoaderConfig config instance')]
    public function testEncodeWithWrongConfigTypeThrowsException(): void
    {
        $this->expectExceptionObject(
            CategoryException::invalidFieldValueType('config', ServiceMenuLoaderConfig::class, StubLoaderConfig::class)
        );

        $this->serializer->encode(new StubLoaderConfig());
    }
}
