<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Shipping\ContentSystem\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Checkout\Shipping\ContentSystem\DataLoader\ShippingMethodLoaderConfig;
use Shopware\Core\Checkout\Shipping\ContentSystem\DataLoader\ShippingMethodLoaderConfigSerializer;
use Shopware\Core\Test\Stub\ContentSystem\StubLoaderConfig;

/**
 * @internal
 */
#[CoversClass(ShippingMethodLoaderConfigSerializer::class)]
class ShippingMethodLoaderConfigSerializerTest extends TestCase
{
    private ShippingMethodLoaderConfigSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new ShippingMethodLoaderConfigSerializer();
    }

    #[TestDox('returns shipping_method source identifier')]
    public function testGetSourceReturnsShippingMethodString(): void
    {
        static::assertSame('shipping_method', ShippingMethodLoaderConfigSerializer::getSource());
    }

    #[TestDox('decodes empty array into ShippingMethodLoaderConfig with onlyAvailable true by default')]
    public function testDecodeEmptyArrayReturnsShippingMethodLoaderConfigWithOnlyAvailableDefault(): void
    {
        $result = $this->serializer->decode([]);

        static::assertInstanceOf(ShippingMethodLoaderConfig::class, $result);
        static::assertTrue($result->onlyAvailable);
    }

    #[TestDox('decodes null associations into ShippingMethodLoaderConfig with empty associations')]
    public function testDecodeNullAssociationsReturnsEmptyAssociations(): void
    {
        $result = $this->serializer->decode(['associations' => null]);

        static::assertInstanceOf(ShippingMethodLoaderConfig::class, $result);
        static::assertSame([], $result->associations);
    }

    #[TestDox('decodes array with valid associations into ShippingMethodLoaderConfig with associations')]
    public function testDecodeWithValidAssociationsReturnsShippingMethodLoaderConfigWithAssociations(): void
    {
        $result = $this->serializer->decode(['associations' => ['country', 'translations']]);

        static::assertInstanceOf(ShippingMethodLoaderConfig::class, $result);
        static::assertSame(['country', 'translations'], $result->associations);
    }

    #[TestDox('decodes array with empty associations list into ShippingMethodLoaderConfig with empty associations')]
    public function testDecodeWithEmptyAssociationsListReturnsShippingMethodLoaderConfigWithEmptyAssociations(): void
    {
        $result = $this->serializer->decode(['associations' => []]);

        static::assertInstanceOf(ShippingMethodLoaderConfig::class, $result);
        static::assertSame([], $result->associations);
    }

    #[TestDox('decodes onlyAvailable false into ShippingMethodLoaderConfig with onlyAvailable set to false')]
    public function testDecodeWithOnlyAvailableFalseAssignsFalse(): void
    {
        $result = $this->serializer->decode(['onlyAvailable' => false]);

        static::assertInstanceOf(ShippingMethodLoaderConfig::class, $result);
        static::assertFalse($result->onlyAvailable);
    }

    #[TestDox('decodes onlyAvailable true into ShippingMethodLoaderConfig with onlyAvailable set to true')]
    public function testDecodeWithOnlyAvailableTrueAssignsTrue(): void
    {
        $result = $this->serializer->decode(['onlyAvailable' => true]);

        static::assertInstanceOf(ShippingMethodLoaderConfig::class, $result);
        static::assertTrue($result->onlyAvailable);
    }

    #[TestDox('throws exception when associations value is not an array')]
    public function testDecodeWithNonArrayAssociationsThrowsException(): void
    {
        $this->expectExceptionObject(
            ContentSystemException::invalidFieldValueType('associations', 'array', 'string')
        );

        $this->serializer->decode(['associations' => 'country']);
    }

    #[TestDox('throws exception when an association item is null')]
    public function testDecodeWithNullAssociationItemThrowsException(): void
    {
        $this->expectExceptionObject(
            ContentSystemException::invalidFieldValueType('associations.0', 'non-empty string', 'NULL')
        );

        $this->serializer->decode(['associations' => [null]]);
    }

    #[TestDox('throws exception when an association item is an empty string')]
    public function testDecodeWithEmptyStringAssociationItemThrowsException(): void
    {
        $this->expectExceptionObject(
            ContentSystemException::invalidFieldValueType('associations.0', 'non-empty string', 'string')
        );

        $this->serializer->decode(['associations' => ['']]);
    }

    #[TestDox('throws exception when onlyAvailable value is not a boolean')]
    public function testDecodeWithNonBoolOnlyAvailableThrowsException(): void
    {
        $this->expectExceptionObject(
            ContentSystemException::invalidFieldValueType('onlyAvailable', 'bool', 'string')
        );

        $this->serializer->decode(['onlyAvailable' => 'yes']);
    }

    #[TestDox('encodes ShippingMethodLoaderConfig with no associations and default onlyAvailable into empty array and omits onlyAvailable key')]
    public function testEncodeConfigWithEmptyAssociationsAndDefaultOnlyAvailableReturnsEmptyArray(): void
    {
        $config = new ShippingMethodLoaderConfig();

        $result = $this->serializer->encode($config);

        static::assertSame([], $result);
    }

    #[TestDox('encodes ShippingMethodLoaderConfig with associations into array with associations key')]
    public function testEncodeConfigWithAssociationsIncludesAssociationsKey(): void
    {
        $config = new ShippingMethodLoaderConfig(associations: ['country', 'translations']);

        $result = $this->serializer->encode($config);

        static::assertSame(['associations' => ['country', 'translations']], $result);
    }

    #[TestDox('encodes ShippingMethodLoaderConfig with onlyAvailable false into array with onlyAvailable key')]
    public function testEncodeConfigWithOnlyAvailableFalseIncludesOnlyAvailableKey(): void
    {
        $config = new ShippingMethodLoaderConfig(onlyAvailable: false);

        $result = $this->serializer->encode($config);

        static::assertSame(['onlyAvailable' => false], $result);
    }

    #[TestDox('encodes ShippingMethodLoaderConfig with associations and onlyAvailable false into full array')]
    public function testEncodeConfigWithAssociationsAndOnlyAvailableFalseReturnsFullArray(): void
    {
        $config = new ShippingMethodLoaderConfig(associations: ['country'], onlyAvailable: false);

        $result = $this->serializer->encode($config);

        static::assertSame(['associations' => ['country'], 'onlyAvailable' => false], $result);
    }

    #[TestDox('round-trips a config with associations without data loss')]
    public function testDecodeAndEncodeAreInverseForConfigWithAssociations(): void
    {
        $original = ['associations' => ['country', 'translations']];

        $config = $this->serializer->decode($original);
        $encoded = $this->serializer->encode($config);

        static::assertSame($original, $encoded);
    }

    #[TestDox('round-trips a config with onlyAvailable false without data loss')]
    public function testDecodeAndEncodeAreInverseForConfigWithOnlyAvailableFalse(): void
    {
        $original = ['onlyAvailable' => false];

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

    #[TestDox('throws exception when encoding a non-ShippingMethodLoaderConfig config instance')]
    public function testEncodeWithWrongConfigTypeThrowsException(): void
    {
        $this->expectExceptionObject(
            ContentSystemException::invalidFieldValueType('config', ShippingMethodLoaderConfig::class, StubLoaderConfig::class)
        );

        $this->serializer->encode(new StubLoaderConfig());
    }
}
