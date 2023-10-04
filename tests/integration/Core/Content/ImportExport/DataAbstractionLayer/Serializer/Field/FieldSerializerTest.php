<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field\PriceSerializer;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
#[Package('services-settings')]
class FieldSerializerTest extends TestCase
{
    use KernelTestBehaviour;

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

        $serialized = $fieldSerializer->serialize($config, $priceField, $value);
        $actual = $serialized instanceof \Traversable ? iterator_to_array($serialized) : (array) $serialized;

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
        static::assertIsArray($actual);
        static::assertArrayHasKey(Defaults::CURRENCY, $actual);
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
        static::assertIsArray($actual);
        static::assertArrayHasKey(Defaults::CURRENCY, $actual);
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
        static::assertIsArray($actual);
        static::assertArrayHasKey(Defaults::CURRENCY, $actual);
        $actualPrice = $actual[Defaults::CURRENCY];

        static::assertFalse($actualPrice['linked']);
        static::assertSame(0.0, $actualPrice['net']);
        static::assertSame(0.0, $actualPrice['gross']);
    }

    /**
     * @param iterable<mixed>|null $iterable
     */
    private function first(?iterable $iterable): mixed
    {
        if ($iterable === null) {
            return null;
        }

        foreach ($iterable as $value) {
            return $value;
        }

        return null;
    }
}
