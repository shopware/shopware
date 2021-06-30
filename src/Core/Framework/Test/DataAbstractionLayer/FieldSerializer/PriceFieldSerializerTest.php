<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\PriceFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;

class PriceFieldSerializerTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @var PriceFieldSerializer
     */
    protected $serializer;

    public function setUp(): void
    {
        $this->serializer = $this->getContainer()->get(PriceFieldSerializer::class);
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

    public function testEncoindingWithMultiplePrices(): void
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

    private function encode(array $data): string
    {
        $field = new PriceField('test', 'test');
        $existence = new EntityExistence('test', ['someId'], true, false, false, []);
        $keyPair = new KeyValuePair('someId', $data, false);
        $bag = new WriteParameterBag(
            $this->getContainer()->get(ProductDefinition::class),
            WriteContext::createFromContext(Context::createDefaultContext()),
            '',
            new WriteCommandQueue()
        );

        $data = iterator_to_array($this->serializer->encode($field, $existence, $keyPair, $bag), true);

        return $data['test'];
    }
}
