<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Hydration\DataLoader\PaymentMethodLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\PaymentMethodLoader\PaymentMethodLoaderConfig;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\PaymentMethodLoader\PaymentMethodLoaderConfigSerializer;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Tests\Unit\Core\Content\ContentSystem\_helper\StubLoaderConfig;

/**
 * @internal
 */
#[CoversClass(PaymentMethodLoaderConfigSerializer::class)]
class PaymentMethodLoaderConfigSerializerTest extends TestCase
{
    private PaymentMethodLoaderConfigSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new PaymentMethodLoaderConfigSerializer();
    }

    #[TestDox('returns payment_method source identifier')]
    public function testGetSourceReturnsPaymentMethodString(): void
    {
        static::assertSame('payment_method', PaymentMethodLoaderConfigSerializer::getSource());
    }

    #[TestDox('decodes empty array into PaymentMethodLoaderConfig with onlyAvailable true by default')]
    public function testDecodeEmptyArrayReturnsPaymentMethodLoaderConfigWithOnlyAvailableDefault(): void
    {
        $result = $this->serializer->decode([]);

        static::assertInstanceOf(PaymentMethodLoaderConfig::class, $result);
        static::assertTrue($result->onlyAvailable);
    }

    #[TestDox('decodes array with valid associations into PaymentMethodLoaderConfig with associations')]
    public function testDecodeWithValidAssociationsReturnsPaymentMethodLoaderConfigWithAssociations(): void
    {
        $result = $this->serializer->decode(['associations' => ['country', 'translations']]);

        static::assertInstanceOf(PaymentMethodLoaderConfig::class, $result);
        static::assertSame(['country', 'translations'], $result->associations);
    }

    #[TestDox('decodes onlyAvailable false into PaymentMethodLoaderConfig with onlyAvailable set to false')]
    public function testDecodeWithOnlyAvailableFalseAssignsFalse(): void
    {
        $result = $this->serializer->decode(['onlyAvailable' => false]);

        static::assertInstanceOf(PaymentMethodLoaderConfig::class, $result);
        static::assertFalse($result->onlyAvailable);
    }

    #[TestDox('decodes onlyAvailable true into PaymentMethodLoaderConfig with onlyAvailable set to true')]
    public function testDecodeWithOnlyAvailableTrueAssignsTrue(): void
    {
        $result = $this->serializer->decode(['onlyAvailable' => true]);

        static::assertInstanceOf(PaymentMethodLoaderConfig::class, $result);
        static::assertTrue($result->onlyAvailable);
    }

    #[TestDox('decodes array with empty associations list into PaymentMethodLoaderConfig with empty associations')]
    public function testDecodeWithEmptyAssociationsListReturnsPaymentMethodLoaderConfigWithEmptyAssociations(): void
    {
        $result = $this->serializer->decode(['associations' => []]);

        static::assertInstanceOf(PaymentMethodLoaderConfig::class, $result);
        static::assertSame([], $result->associations);
    }

    #[TestDox('decodes null associations into PaymentMethodLoaderConfig with empty associations')]
    public function testDecodeNullAssociationsReturnsEmptyAssociations(): void
    {
        $result = $this->serializer->decode(['associations' => null]);

        static::assertInstanceOf(PaymentMethodLoaderConfig::class, $result);
        static::assertSame([], $result->associations);
    }

    #[TestDox('encodes default config into empty array omitting both keys')]
    public function testEncodeConfigWithDefaultValuesReturnsEmptyArray(): void
    {
        $config = new PaymentMethodLoaderConfig();

        $result = $this->serializer->encode($config);

        static::assertSame([], $result);
    }

    #[TestDox('encodes PaymentMethodLoaderConfig with associations into array with associations key')]
    public function testEncodeConfigWithAssociationsIncludesAssociationsKey(): void
    {
        $config = new PaymentMethodLoaderConfig(associations: ['country', 'translations']);

        $result = $this->serializer->encode($config);

        static::assertSame(['associations' => ['country', 'translations']], $result);
    }

    #[TestDox('encodes PaymentMethodLoaderConfig with onlyAvailable false into array with onlyAvailable key')]
    public function testEncodeConfigWithOnlyAvailableFalseIncludesOnlyAvailableKey(): void
    {
        $config = new PaymentMethodLoaderConfig(onlyAvailable: false);

        $result = $this->serializer->encode($config);

        static::assertSame(['onlyAvailable' => false], $result);
    }

    #[TestDox('encodes PaymentMethodLoaderConfig with associations and onlyAvailable false into full array')]
    public function testEncodeConfigWithAssociationsAndOnlyAvailableFalseReturnsFullArray(): void
    {
        $config = new PaymentMethodLoaderConfig(associations: ['country'], onlyAvailable: false);

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

    #[TestDox('throws exception when encoding a non-PaymentMethodLoaderConfig config instance')]
    public function testEncodeWithWrongConfigTypeThrowsException(): void
    {
        $this->expectExceptionObject(
            ContentSystemException::invalidFieldValueType('config', PaymentMethodLoaderConfig::class, StubLoaderConfig::class)
        );

        $this->serializer->encode(new StubLoaderConfig());
    }

    #[TestDox('throws DecorationPatternException when getDecorated is called')]
    public function testGetDecoratedThrowsDecorationPatternException(): void
    {
        $this->expectExceptionObject(new DecorationPatternException(PaymentMethodLoaderConfigSerializer::class));

        $this->serializer->getDecorated();
    }
}
