<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\PriceFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\Context\ExecutionContextFactory;
use Symfony\Component\Validator\Mapping\Factory\BlackHoleMetadataFactory;
use Symfony\Component\Validator\Validator\RecursiveValidator;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PriceFieldSerializer::class)]
class PriceFieldSerializerTest extends TestCase
{
    protected PriceFieldSerializer $serializer;

    protected function setUp(): void
    {
        $validator = new RecursiveValidator(
            new ExecutionContextFactory(
                static::createStub(TranslatorInterface::class)
            ),
            new BlackHoleMetadataFactory(),
            new ConstraintValidatorFactory()
        );

        $this->serializer = new PriceFieldSerializer(
            $validator,
            new StaticDefinitionInstanceRegistry(
                [
                    new ProductDefinition(),
                ],
                $validator,
                static::createStub(EntityWriteGateway::class)
            )
        );
    }

    public function testSerializeStrings(): void
    {
        $data = $this->encode([
            Defaults::CURRENCY => [
                'net' => '5',
                'gross' => '5',
                'currencyId' => Defaults::CURRENCY,
                'linked' => true,
            ],
        ]);

        static::assertSame('{"cb7d2554b0ce847cd82f3ac9bd1c0dfca":{"net":5.0,"gross":5.0,"currencyId":"b7d2554b0ce847cd82f3ac9bd1c0dfca","linked":true}}', $data);
    }

    public function testSerializeCollection(): void
    {
        $price = new Price(Defaults::CURRENCY, 5, 5, true);
        $collection = new PriceCollection();
        $collection->add($price);

        $data = $this->encode($collection);

        static::assertSame('{"cb7d2554b0ce847cd82f3ac9bd1c0dfca":{"currencyId":"b7d2554b0ce847cd82f3ac9bd1c0dfca","net":5.0,"gross":5.0,"linked":true,"listPrice":null,"regulationPrice":null}}', $data);
    }

    public function testRequiresDefaultCurrency(): void
    {
        $e = null;

        try {
            $this->encode([
                [
                    'net' => '5.5',
                    'gross' => '5.5',
                    'currencyId' => Uuid::randomHex(),
                    'linked' => true,
                ],
            ]);
        } catch (WriteConstraintViolationException $e) {
            static::assertCount(1, $e->getViolations());
            static::assertSame('No price for default currency defined', $e->getViolations()->get(0)->getMessage());
            static::assertSame('/test', $e->getViolations()->get(0)->getPropertyPath());
        }

        static::assertNotNull($e);
    }

    public function testSerializeStringsFloat(): void
    {
        $data = $this->encode([
            Defaults::CURRENCY => [
                'net' => '5.5',
                'gross' => '5.5',
                'currencyId' => Defaults::CURRENCY,
                'linked' => true,
            ],
        ]);

        static::assertSame('{"cb7d2554b0ce847cd82f3ac9bd1c0dfca":{"net":5.5,"gross":5.5,"currencyId":"b7d2554b0ce847cd82f3ac9bd1c0dfca","linked":true}}', $data);
    }

    public function testEncodingWithMultiplePrices(): void
    {
        $data = $this->encode([
            [
                'net' => '5.5',
                'gross' => '5.5',
                'currencyId' => Defaults::CURRENCY,
                'linked' => true,
            ],
            [
                'net' => '5.5',
                'gross' => '5.5',
                'currencyId' => 'a27e053a364e428fa0f5e4d208bbecc7',
                'linked' => true,
            ],
        ]);

        static::assertSame($data, '{"cb7d2554b0ce847cd82f3ac9bd1c0dfca":{"net":5.5,"gross":5.5,"currencyId":"b7d2554b0ce847cd82f3ac9bd1c0dfca","linked":true},"ca27e053a364e428fa0f5e4d208bbecc7":{"net":5.5,"gross":5.5,"currencyId":"a27e053a364e428fa0f5e4d208bbecc7","linked":true}}');
    }

    public function testSerializeFloat(): void
    {
        $data = $this->encode([
            Defaults::CURRENCY => [
                'net' => 5.2,
                'gross' => 5.2,
                'currencyId' => Defaults::CURRENCY,
                'linked' => true,
            ],
        ]);

        static::assertSame('{"cb7d2554b0ce847cd82f3ac9bd1c0dfca":{"net":5.2,"gross":5.2,"currencyId":"b7d2554b0ce847cd82f3ac9bd1c0dfca","linked":true}}', $data);
    }

    public function testSerializeInt(): void
    {
        $data = $this->encode([
            Defaults::CURRENCY => [
                'net' => 5,
                'gross' => 5,
                'currencyId' => Defaults::CURRENCY,
                'linked' => true,
            ],
        ]);

        static::assertSame('{"cb7d2554b0ce847cd82f3ac9bd1c0dfca":{"net":5.0,"gross":5.0,"currencyId":"b7d2554b0ce847cd82f3ac9bd1c0dfca","linked":true}}', $data);
    }

    public function testSerializeNegativeInt(): void
    {
        $data = $this->encode([
            Defaults::CURRENCY => [
                'net' => -5,
                'gross' => -5,
                'currencyId' => Defaults::CURRENCY,
                'linked' => true,
            ],
        ]);

        static::assertSame('{"cb7d2554b0ce847cd82f3ac9bd1c0dfca":{"net":-5.0,"gross":-5.0,"currencyId":"b7d2554b0ce847cd82f3ac9bd1c0dfca","linked":true}}', $data);
    }

    public function testSerializeNegativeFloat(): void
    {
        $data = $this->encode([
            Defaults::CURRENCY => [
                'net' => -5.7,
                'gross' => -5.7,
                'currencyId' => Defaults::CURRENCY,
                'linked' => true,
            ],
        ]);

        static::assertSame('{"cb7d2554b0ce847cd82f3ac9bd1c0dfca":{"net":-5.7,"gross":-5.7,"currencyId":"b7d2554b0ce847cd82f3ac9bd1c0dfca","linked":true}}', $data);
    }

    public function testSerializeWithListPrice(): void
    {
        $data = $this->encode([
            Defaults::CURRENCY => [
                'net' => '5',
                'gross' => '5',
                'currencyId' => Defaults::CURRENCY,
                'linked' => true,
                'listPrice' => [
                    'net' => '10',
                    'gross' => '10',
                    'currencyId' => Defaults::CURRENCY,
                    'linked' => true,
                ],
            ],
        ]);

        $json = '{"cb7d2554b0ce847cd82f3ac9bd1c0dfca":{"net":5.0,"gross":5.0,"currencyId":"b7d2554b0ce847cd82f3ac9bd1c0dfca","linked":true,"listPrice":{"net":"10","gross":"10","currencyId":"b7d2554b0ce847cd82f3ac9bd1c0dfca","linked":true},"percentage":{"net":50.0,"gross":50.0}}}';
        static::assertSame($json, $data);
    }

    public function testSerializeWithRegulationPrice(): void
    {
        $data = $this->encode([
            Defaults::CURRENCY => [
                'net' => '5',
                'gross' => '5',
                'currencyId' => Defaults::CURRENCY,
                'linked' => true,
                'regulationPrice' => [
                    'net' => '20',
                    'gross' => '20',
                    'currencyId' => Defaults::CURRENCY,
                    'linked' => true,
                ],
            ],
        ]);

        $json = '{"cb7d2554b0ce847cd82f3ac9bd1c0dfca":{"net":5.0,"gross":5.0,"currencyId":"b7d2554b0ce847cd82f3ac9bd1c0dfca","linked":true,"regulationPrice":{"net":"20","gross":"20","currencyId":"b7d2554b0ce847cd82f3ac9bd1c0dfca","linked":true}}}';
        static::assertSame($json, $data);
    }

    public function testSerializeWithZeroNetListPrice(): void
    {
        $data = $this->encode([
            Defaults::CURRENCY => [
                'net' => '5',
                'gross' => '5',
                'currencyId' => Defaults::CURRENCY,
                'linked' => true,
                'listPrice' => [
                    'net' => '0',
                    'gross' => '10',
                    'currencyId' => Defaults::CURRENCY,
                    'linked' => true,
                ],
            ],
        ]);

        $json = '{"cb7d2554b0ce847cd82f3ac9bd1c0dfca":{"net":5.0,"gross":5.0,"currencyId":"b7d2554b0ce847cd82f3ac9bd1c0dfca","linked":true,"listPrice":{"net":"0","gross":"10","currencyId":"b7d2554b0ce847cd82f3ac9bd1c0dfca","linked":true},"percentage":{"net":0.0,"gross":50.0}}}';
        static::assertSame($json, $data);
    }

    public function testSerializeWithZeroGrossListPrice(): void
    {
        $data = $this->encode([
            Defaults::CURRENCY => [
                'net' => '5',
                'gross' => '5',
                'currencyId' => Defaults::CURRENCY,
                'linked' => true,
                'listPrice' => [
                    'net' => '10',
                    'gross' => '0',
                    'currencyId' => Defaults::CURRENCY,
                    'linked' => true,
                ],
            ],
        ]);

        $json = '{"cb7d2554b0ce847cd82f3ac9bd1c0dfca":{"net":5.0,"gross":5.0,"currencyId":"b7d2554b0ce847cd82f3ac9bd1c0dfca","linked":true,"listPrice":{"net":"10","gross":"0","currencyId":"b7d2554b0ce847cd82f3ac9bd1c0dfca","linked":true},"percentage":{"net":50.0,"gross":0.0}}}';
        static::assertSame($json, $data);
    }

    public function testSerializeWithZeroListPrice(): void
    {
        $data = $this->encode([
            Defaults::CURRENCY => [
                'net' => '5',
                'gross' => '5',
                'currencyId' => Defaults::CURRENCY,
                'linked' => true,
                'listPrice' => [
                    'net' => '0',
                    'gross' => '0',
                    'currencyId' => Defaults::CURRENCY,
                    'linked' => true,
                ],
            ],
        ]);

        $json = '{"cb7d2554b0ce847cd82f3ac9bd1c0dfca":{"net":5.0,"gross":5.0,"currencyId":"b7d2554b0ce847cd82f3ac9bd1c0dfca","linked":true,"listPrice":{"net":"0","gross":"0","currencyId":"b7d2554b0ce847cd82f3ac9bd1c0dfca","linked":true},"percentage":null}}';
        static::assertSame($json, $data);
    }

    public function testSerializeWithNoListPriceUnsetsPercentage(): void
    {
        $data = $this->encode([
            Defaults::CURRENCY => [
                'net' => '5',
                'gross' => '5',
                'currencyId' => Defaults::CURRENCY,
                'linked' => true,
                'listPrice' => null,
                'percentage' => [
                    'net' => '50.0',
                    'gross' => '50.0',
                ],
            ],
        ]);

        $json = '{"cb7d2554b0ce847cd82f3ac9bd1c0dfca":{"net":5.0,"gross":5.0,"currencyId":"b7d2554b0ce847cd82f3ac9bd1c0dfca","linked":true,"listPrice":null}}';
        static::assertSame($json, $data);
    }

    public function testSerializeWithWrongPayloadThrows(): void
    {
        static::expectException(WriteConstraintViolationException::class);

        $this->encode([
            Defaults::CURRENCY => [
                'gross' => '5',
                'currencyId' => Defaults::CURRENCY,
                'linked' => true,
            ],
        ]);
    }

    public function testSerializeWithWrongTypeThrows(): void
    {
        static::expectException(WriteConstraintViolationException::class);

        $this->encode([
            Defaults::CURRENCY => 'foo',
        ]);
    }

    #[DataProvider('nonArrayValueProvider')]
    public function testSerializeNonArrayValueThrows(mixed $value): void
    {
        try {
            $this->encode($value);

            static::fail(WriteConstraintViolationException::class . ' not thrown.');
        } catch (WriteConstraintViolationException $e) {
            static::assertCount(1, $e->getViolations()->findByCodes(Type::INVALID_TYPE_ERROR));
            static::assertSame('/someId', $e->getViolations()->get(0)->getPropertyPath());
        }
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function nonArrayValueProvider(): iterable
    {
        yield 'float' => [12.5];
        yield 'string' => ['2025-10-09'];
        yield 'int' => [12];
        yield 'bool' => [true];
        yield 'zero' => [0];
        yield 'empty string' => [''];
        yield 'numeric string' => ['12.50'];
    }

    public function testSerializeEmptyArrayStillRequiresDefaultCurrency(): void
    {
        try {
            $this->encode([]);

            static::fail(WriteConstraintViolationException::class . ' not thrown.');
        } catch (WriteConstraintViolationException $e) {
            static::assertSame('No price for default currency defined', $e->getViolations()->get(0)->getMessage());
        }
    }

    /**
     * The array check runs before the `NotBlank` check, and `Type` deliberately lets `null` pass so a
     * required-but-missing price is still reported as blank rather than as a type mismatch.
     */
    public function testSerializeNullOnRequiredFieldStillReportsBlank(): void
    {
        $field = (new PriceField('test', 'test'))->addFlags(new Required());

        try {
            $this->encode(null, $field);

            static::fail(WriteConstraintViolationException::class . ' not thrown.');
        } catch (WriteConstraintViolationException $e) {
            static::assertCount(1, $e->getViolations()->findByCodes(NotBlank::IS_BLANK_ERROR));
            static::assertCount(0, $e->getViolations()->findByCodes(Type::INVALID_TYPE_ERROR));
        }
    }

    public function testDecodeIsBackwardCompatible(): void
    {
        $json = '{"cb7d2554b0ce847cd82f3ac9bd1c0dfca":{"net":5.0,"gross":5.0,"currencyId":"b7d2554b0ce847cd82f3ac9bd1c0dfca","linked":true,"listPrice":{"net":"10","gross":"10","currencyId":"b7d2554b0ce847cd82f3ac9bd1c0dfca","linked":true},"regulationPrice":{"net":"10","gross":"10","currencyId":"b7d2554b0ce847cd82f3ac9bd1c0dfca","linked":true}}}';

        $field = new PriceField('test', 'test');

        $decoded = $this->serializer->decode($field, $json);

        static::assertInstanceOf(PriceCollection::class, $decoded);
        $price = $decoded->get(Defaults::CURRENCY);

        static::assertInstanceOf(Price::class, $price);
        static::assertSame(5.0, $price->getNet());
        static::assertSame(5.0, $price->getGross());
        static::assertInstanceOf(Price::class, $price->getListPrice());
        static::assertSame(10.0, $price->getListPrice()->getNet());
        static::assertSame(10.0, $price->getListPrice()->getGross());
        static::assertInstanceOf(Price::class, $price->getRegulationPrice());
        static::assertSame(10.0, $price->getRegulationPrice()->getNet());
        static::assertSame(10.0, $price->getRegulationPrice()->getGross());

        static::assertNull($price->getPercentage());
    }

    private function encode(mixed $data, ?PriceField $field = null): string
    {
        $field ??= new PriceField('test', 'test');
        $existence = new EntityExistence('product', ['someId' => true], true, false, false, []);
        $keyPair = new KeyValuePair('someId', $data, false);
        $bag = new WriteParameterBag(
            new ProductDefinition(),
            WriteContext::createFromContext(Context::createDefaultContext()),
            '',
            new WriteCommandQueue()
        );

        $data = iterator_to_array($this->serializer->encode($field, $existence, $keyPair, $bag), true);

        return $data['test'];
    }
}
