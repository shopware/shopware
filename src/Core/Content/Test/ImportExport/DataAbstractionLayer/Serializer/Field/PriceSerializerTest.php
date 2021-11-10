<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\ImportExport\DataAbstractionLayer\Serializer\Field;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field\PriceSerializer;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\System\Currency\CurrencyDefinition;

class PriceSerializerTest extends TestCase
{
    use KernelTestBehaviour;

    private EntityRepositoryInterface $currencyRepository;

    protected function setUp(): void
    {
        $this->currencyRepository = $this->getContainer()->get(CurrencyDefinition::ENTITY_NAME . '.repository');
    }

    public function testSerializePrice(): void
    {
        $priceField = new PriceField('price', 'price');

        $priceSerializer = new PriceSerializer($this->currencyRepository);
        $config = new Config([], [], []);

        $price = new Price(Defaults::CURRENCY, 10.0, 10.0, false);

        $expectedSerialized = [
            'EUR' => [
                'currencyId' => Defaults::CURRENCY,
                'net' => 10.0,
                'gross' => 10.0,
                'linked' => false,
                'listPrice' => null,
                'percentage' => null,
                'extensions' => [],
            ],
            'DEFAULT' => [
                'currencyId' => Defaults::CURRENCY,
                'net' => 10.0,
                'gross' => 10.0,
                'linked' => false,
                'listPrice' => null,
                'percentage' => null,
                'extensions' => [],
            ],
        ];
        static::assertNull($this->first($priceSerializer->serialize($config, $priceField, [])));
        $serializedPrice = $this->first($priceSerializer->serialize($config, $priceField, [$price]));
        static::assertSame($expectedSerialized, $serializedPrice);

        $expectedDeserialized = [
            Defaults::CURRENCY => [
                'currencyId' => Defaults::CURRENCY,
                'net' => 10.0,
                'gross' => 10.0,
                'linked' => false,
                'listPrice' => null,
                'percentage' => null,
                'extensions' => [],
            ],
        ];
        static::assertEmpty($priceSerializer->deserialize($config, $priceField, ''));
        $deserializedPrice = $priceSerializer->deserialize($config, $priceField, $expectedSerialized);
        static::assertSame($expectedDeserialized, $deserializedPrice);
    }

    public function testSerializeListPrice(): void
    {
        $priceField = new PriceField('price', 'price');

        $priceSerializer = new PriceSerializer($this->currencyRepository);
        $config = new Config([], [], []);

        $listPrice = new Price(Defaults::CURRENCY, 11.0, 11.0, false);
        $price = new Price(Defaults::CURRENCY, 10.0, 10.0, false, $listPrice);

        $expectedSerialized = [
            'EUR' => [
                'currencyId' => Defaults::CURRENCY,
                'net' => 10.0,
                'gross' => 10.0,
                'linked' => false,
                'listPrice' => [
                    'currencyId' => Defaults::CURRENCY,
                    'net' => 11.0,
                    'gross' => 11.0,
                    'linked' => false,
                    'listPrice' => null,
                    'percentage' => null,
                    'extensions' => [],
                ],
                'percentage' => null,
                'extensions' => [],
            ],
            'DEFAULT' => [
                'currencyId' => Defaults::CURRENCY,
                'net' => 10.0,
                'gross' => 10.0,
                'linked' => false,
                'listPrice' => [
                    'currencyId' => Defaults::CURRENCY,
                    'net' => 11.0,
                    'gross' => 11.0,
                    'linked' => false,
                    'listPrice' => null,
                    'percentage' => null,
                    'extensions' => [],
                ],
                'percentage' => null,
                'extensions' => [],
            ],
        ];
        static::assertNull($this->first($priceSerializer->serialize($config, $priceField, [])));
        $serializedPrice = $this->first($priceSerializer->serialize($config, $priceField, [$price]));
        static::assertSame($expectedSerialized, $serializedPrice);

        $expectedDeserialized = [
            Defaults::CURRENCY => [
                'currencyId' => Defaults::CURRENCY,
                'net' => 10.0,
                'gross' => 10.0,
                'linked' => false,
                'listPrice' => [
                    'currencyId' => Defaults::CURRENCY,
                    'net' => 11.0,
                    'gross' => 11.0,
                    'linked' => false,
                    'listPrice' => null,
                    'percentage' => null,
                    'extensions' => [],
                ],
                'percentage' => null,
                'extensions' => [],
            ],
        ];
        static::assertEmpty($priceSerializer->deserialize($config, $priceField, ''));
        $deserializedPrice = $priceSerializer->deserialize($config, $priceField, $expectedSerialized);
        static::assertSame($expectedDeserialized, $deserializedPrice);
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
