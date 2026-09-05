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

    public function testRoundTripsPropertyConfig(): void
    {
        $serializer = new ProductConfiguratorLoaderConfigSerializer();

        static::assertSame(['productProperty' => 'product'], $serializer->encode($serializer->decode(['productProperty' => 'product'])));
    }

    public function testRejectsInvalidProperty(): void
    {
        $serializer = new ProductConfiguratorLoaderConfigSerializer();

        static::expectExceptionObject(ProductException::invalidFieldValueType('productProperty', 'non-empty string', 'integer'));
        $serializer->decode(['productProperty' => 42]);
    }

    public function testRejectsWrongConfigType(): void
    {
        $serializer = new ProductConfiguratorLoaderConfigSerializer();

        static::expectExceptionObject(ProductException::invalidFieldValueType('config', ProductConfiguratorLoaderConfig::class, StubLoaderConfig::class));
        $serializer->encode(new StubLoaderConfig());
    }
}
