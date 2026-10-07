<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\ContentSystem\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductConfiguratorLoaderConfig;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductConfiguratorLoaderConfigSerializer;
use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\StubLoaderConfig;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ProductConfiguratorLoaderConfigSerializer::class)]
class ProductConfiguratorLoaderConfigSerializerTest extends TestCase
{
    public function testRoundTripsEmptyConfig(): void
    {
        $serializer = new ProductConfiguratorLoaderConfigSerializer();

        static::assertSame([], $serializer->encode($serializer->decode([])));
    }

    public function testRoundTripsProductIdConfig(): void
    {
        $serializer = new ProductConfiguratorLoaderConfigSerializer();

        static::assertSame(['productId' => 'productId'], $serializer->encode($serializer->decode(['productId' => 'productId'])));
    }

    public function testRejectsInvalidProductId(): void
    {
        $serializer = new ProductConfiguratorLoaderConfigSerializer();

        static::expectExceptionObject(ProductException::invalidFieldValueType('productId', 'non-empty string', 'integer'));
        $serializer->decode(['productId' => 42]);
    }

    public function testRejectsWrongConfigType(): void
    {
        $serializer = new ProductConfiguratorLoaderConfigSerializer();

        static::expectExceptionObject(ProductException::invalidFieldValueType('config', ProductConfiguratorLoaderConfig::class, StubLoaderConfig::class));
        $serializer->encode(new StubLoaderConfig());
    }
}
