<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Hydration\DataLoader\PaymentMethodLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\PaymentMethodLoader\PaymentMethodLoaderConfig;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\PaymentMethodLoader\PaymentMethodLoaderConfigSerializer;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

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

    #[TestDox('decodes empty array into PaymentMethodLoaderConfig with empty associations and onlyAvailable true')]
    public function testDecodeEmptyArrayReturnsPaymentMethodLoaderConfigWithDefaults(): void
    {
        $result = $this->serializer->decode([]);

        static::assertInstanceOf(PaymentMethodLoaderConfig::class, $result);
        static::assertSame([], $result->associations);
        static::assertTrue($result->onlyAvailable);
    }

    #[TestDox('decodes array with valid associations into PaymentMethodLoaderConfig with associations')]
    public function testDecodeWithValidAssociationsReturnsPaymentMethodLoaderConfigWithAssociations(): void
    {
        $result = $this->serializer->decode(['associations' => ['country', 'translations']]);

        static::assertInstanceOf(PaymentMethodLoaderConfig::class, $result);
        static::assertSame(['country', 'translations'], $result->associations);
    }

    #[TestDox('decodes onlyAvailable boolean value into PaymentMethodLoaderConfig')]
    public function testDecodeWithOnlyAvailableBooleanAssignsValue(): void
    {
        $resultFalse = $this->serializer->decode(['onlyAvailable' => false]);
        $resultTrue = $this->serializer->decode(['onlyAvailable' => true]);

        static::assertInstanceOf(PaymentMethodLoaderConfig::class, $resultFalse);
        static::assertFalse($resultFalse->onlyAvailable);
        static::assertInstanceOf(PaymentMethodLoaderConfig::class, $resultTrue);
        static::assertTrue($resultTrue->onlyAvailable);
    }

    #[TestDox('decodes array with empty associations list into PaymentMethodLoaderConfig with empty associations')]
    public function testDecodeWithEmptyAssociationsListReturnsPaymentMethodLoaderConfigWithEmptyAssociations(): void
    {
        $result = $this->serializer->decode(['associations' => []]);

        static::assertInstanceOf(PaymentMethodLoaderConfig::class, $result);
        static::assertSame([], $result->associations);
    }

    #[TestDox('decodes array with null associations into PaymentMethodLoaderConfig with empty associations')]
    public function testDecodeWithNullAssociationsReturnsPaymentMethodLoaderConfigWithEmptyAssociations(): void
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
        static::assertArrayNotHasKey('onlyAvailable', $result);
        static::assertArrayNotHasKey('associations', $result);
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
        $this->expectException(ContentSystemException::class);
        $this->expectExceptionMessage('Field associations expected array');

        $this->serializer->decode(['associations' => 'country']);
    }

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('invalidAssociationItemProvider')]
    #[TestDox('throws exception when an association item is invalid')]
    public function testDecodeWithInvalidAssociationItemThrowsException(array $data): void
    {
        $this->expectException(ContentSystemException::class);
        $this->expectExceptionMessage('Field associations.');

        $this->serializer->decode($data);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function invalidAssociationItemProvider(): array
    {
        return [
            'non-string item (null) triggers type error' => [['associations' => [null]]],
            'empty string item triggers empty check' => [['associations' => ['']]],
        ];
    }

    #[TestDox('throws exception when onlyAvailable value is not a boolean')]
    public function testDecodeWithNonBoolOnlyAvailableThrowsException(): void
    {
        $this->expectException(ContentSystemException::class);
        $this->expectExceptionMessage('Field onlyAvailable expected bool');

        $this->serializer->decode(['onlyAvailable' => 'yes']);
    }

    #[TestDox('throws exception when encoding a non-PaymentMethodLoaderConfig config instance')]
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

    #[TestDox('throws DecorationPatternException when getDecorated is called')]
    public function testGetDecoratedThrowsDecorationPatternException(): void
    {
        $this->expectException(DecorationPatternException::class);
        $this->expectExceptionMessage('The getDecorated() function of core class');

        $this->serializer->getDecorated();
    }
}
