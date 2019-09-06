<?php declare(strict_types=1);

namespace Shopware\Core\System\Test;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Tax\TaxEntity;

trait TaxFixtures
{
    use EntityFixturesBase;

    /**
     * @var array
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
                        'customerGroupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
                    ],
                ],
            ],
        ];
    }

    public function getTaxNineteenPercent(): TaxEntity
    {
        return $this->getTaxFixture('NineteenPercentTax');
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
