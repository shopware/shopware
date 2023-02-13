<?php declare(strict_types=1);

namespace Shopware\Core\System\Test;

use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Tax\TaxEntity;
use Shopware\Core\Test\TestDefaults;

trait TaxFixtures
{
    use EntityFixturesBase;

    /**
     * @var array<string, mixed>
     */
    public $taxFixtures;

    /**
     * @before
     */
    public function initializeTaxFixtures(): void
    {
        $this->taxFixtures = [
            'NineteenPercentTax' => [
                'id' => Uuid::randomHex(),
                'name' => 'NineteenPercentTax',
                'taxRate' => 19,
            ],
            'NineteenPercentTaxWithAreaRule' => [
                'id' => Uuid::randomHex(),
                'name' => 'foo tax',
                'taxRate' => 20,
                'areaRules' => [
                    [
                        'id' => Uuid::randomHex(),
                        'taxRate' => 99,
                        'active' => true,
                        'name' => 'required',
                        'customerGroupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                    ],
                ],
            ],
            'SeventeenPointOnePercentTax' => [
                'id' => Uuid::randomHex(),
                'name' => 'SeventeenPointOnePercentTax',
                'taxRate' => 17.1,
                'areaRules' => [
                    [
                        'id' => Uuid::randomHex(),
                        'taxRate' => 17.1,
                        'active' => true,
                        'name' => 'required',
                        'customerGroupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                    ],
                ],
            ],
            'NinePointSevenFourPercentTax' => [
                'id' => Uuid::randomHex(),
                'name' => 'NinePointSevenFourPercentTax',
                'taxRate' => 9.74,
                'areaRules' => [
                    [
                        'id' => Uuid::randomHex(),
                        'taxRate' => 9.74,
                        'active' => true,
                        'name' => 'required',
                        'customerGroupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                    ],
                ],
            ],
            'ThirteenPointFourEightSixPercentTax' => [
                'id' => Uuid::randomHex(),
                'name' => 'ThirteenPointFourEightSixPercentTax',
                'taxRate' => 13.486,
                'areaRules' => [
                    [
                        'id' => Uuid::randomHex(),
                        'taxRate' => 13.486,
                        'active' => true,
                        'name' => 'required',
                        'customerGroupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                    ],
                ],
            ],
        ];
    }

    public function getTaxNineteenPercent(): TaxEntity
    {
        return $this->getTaxFixture('NineteenPercentTax');
    }

    public function getTaxSeventeenPointOnePercent(): TaxEntity
    {
        return $this->getTaxFixture('SeventeenPointOnePercentTax');
    }

    public function getTaxNinePointSevenFourPercent(): TaxEntity
    {
        return $this->getTaxFixture('NinePointSevenFourPercentTax');
    }

    public function getTaxThirteenPointFourEightSixPercent(): TaxEntity
    {
        return $this->getTaxFixture('ThirteenPointFourEightSixPercentTax');
    }

    public function getTaxNineteenPercentWithAreaRule(): TaxEntity
    {
        return $this->getTaxFixture('NineteenPercentTaxWithAreaRule');
    }

    private function getTaxFixture(string $fixtureName): TaxEntity
    {
        /** @var TaxEntity $taxEntity */
        $taxEntity = $this->createFixture(
            $fixtureName,
            $this->taxFixtures,
            self::getFixtureRepository('tax')
        );

        return $taxEntity;
    }
}
