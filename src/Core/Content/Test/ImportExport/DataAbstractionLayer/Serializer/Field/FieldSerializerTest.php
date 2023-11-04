<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\ImportExport\DataAbstractionLayer\Serializer\Field;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field\FieldSerializer;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field\PriceSerializer;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
#[Package('system-settings')]
class FieldSerializerTest extends TestCase
{
    use KernelTestBehaviour;

    public function testIntField(): void
    {
        $intField = new IntField('foo', 'foo');

        $fieldSerializer = new FieldSerializer();
        $config = new Config([], [], []);

        static::assertNull($this->first($fieldSerializer->serialize($config, $intField, null)));
        static::assertSame('0', $this->first($fieldSerializer->serialize($config, $intField, 0)));
        static::assertSame('3123412344321', $this->first($fieldSerializer->serialize($config, $intField, 3123412344321)));

        static::assertEmpty($fieldSerializer->deserialize($config, $intField, ''));
        static::assertSame(0, $fieldSerializer->deserialize($config, $intField, '0'));
        static::assertSame(3123412344321, $fieldSerializer->deserialize($config, $intField, '3123412344321'));
    }

    public function testPriceField(): void
    {
        $priceField = new PriceField('price', 'price');

        $fieldSerializer = new PriceSerializer($this->getContainer()->get('currency.repository'));
        $config = new Config([], [], []);

        static::assertNull($this->first($fieldSerializer->serialize($config, $priceField, [])));

        $value = [
            [
                'currencyId' => Defaults::CURRENCY,
                'gross' => 11,
                'net' => 10,
            ],
        ];

        $actual = iterator_to_array($fieldSerializer->serialize($config, $priceField, $value));
        static::assertArrayHasKey('price', $actual);
        $actualPrice = $actual['price'];

        static::assertNotEmpty($actualPrice);

        static::assertArrayHasKey('EUR', $actualPrice);
        static::assertArrayHasKey('DEFAULT', $actualPrice);

        static::assertSame($actualPrice['EUR'], $actualPrice['DEFAULT']);
        static::assertSame($value[0]['gross'], $actualPrice['EUR']['gross']);
        static::assertSame($value[0]['net'], $actualPrice['EUR']['net']);

        static::assertEmpty($fieldSerializer->deserialize($config, $priceField, ''));
        static::assertEmpty($fieldSerializer->deserialize($config, $priceField, []));

        $serializedPrice = [
            'DEFAULT' => [
                'gross' => '',
                'net' => '',
            ],
        ];
        $actual = $fieldSerializer->deserialize($config, $priceField, $serializedPrice);
        static::assertNull($actual);

        $serializedPrice = [
            'DEFAULT' => [
                'gross' => '    ',
                'net' => '6.5',
            ],
        ];
        $actual = $fieldSerializer->deserialize($config, $priceField, $serializedPrice);
        static::assertNull($actual);

        $serializedPrice = [
            'DEFAULT' => [
                'gross' => '124.234',
            ],
        ];
        $actual = $fieldSerializer->deserialize($config, $priceField, $serializedPrice);
        static::assertNull($actual);

        $serializedPrice = [
            'DEFAULT' => [
                'net' => '124.234',
            ],
        ];
        $actual = $fieldSerializer->deserialize($config, $priceField, $serializedPrice);
        static::assertNull($actual);

        $serializedPrice = [
            'DEFAULT' => [
                'net' => '124.234',
                'gross' => '122.798',
                'linked' => '0',
            ],
        ];
        $actual = $fieldSerializer->deserialize($config, $priceField, $serializedPrice);
        $actualPrice = $actual[Defaults::CURRENCY];

        static::assertFalse($actualPrice['linked']);
        static::assertSame(124.234, $actualPrice['net']);
        static::assertSame(122.798, $actualPrice['gross']);

        $serializedPrice = [
            'DEFAULT' => [
                'net' => '124',
                'gross' => '122',
                'linked' => '0',
            ],
        ];
        $actual = $fieldSerializer->deserialize($config, $priceField, $serializedPrice);
        $actualPrice = $actual[Defaults::CURRENCY];

        static::assertFalse($actualPrice['linked']);
        static::assertSame(124.0, $actualPrice['net']);
        static::assertSame(122.0, $actualPrice['gross']);

        $serializedPrice = [
            'DEFAULT' => [
                'net' => '0',
                'gross' => '0',
                'linked' => '0',
            ],
        ];
        $actual = $fieldSerializer->deserialize($config, $priceField, $serializedPrice);
        $actualPrice = $actual[Defaults::CURRENCY];

        static::assertFalse($actualPrice['linked']);
        static::assertSame(0.0, $actualPrice['net']);
        static::assertSame(0.0, $actualPrice['gross']);
    }

    private function first(?iterable $iterable)
    {
        if ($iterable === null) {
            return null;
        }

        foreach ($iterable as $value) {
            return $value;
        }
    }
}
