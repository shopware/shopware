<?php declare(strict_types=1);

namespace Shopware\Core\System\Test;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\System\Tax\TaxStruct;

trait TaxFixtures
{
    use EntityFixturesBase;
    /**
     * @var array
     */
    public static $taxFixtures;

    /**
     * @var EntityRepository
     */
    public static $taxFixtureRepository;

    /**
     * @beforeClass
     */
    public static function initializeTaxFixtures(): void
    {
        TaxFixtures::$taxFixtures = [
            'NineteenPercentTax' => [
                    'id' => Uuid::uuid4()->getHex(),
                    'name' => 'NineteenPercentTax',
                    'taxRate' => 19,
                ],
            'NineteenPercentTaxWithAreaRule' => [
                    'id' => Uuid::uuid4()->getHex(),
                    'name' => 'foo tax',
                    'taxRate' => 20,
                    'areaRules' => [
                            [
                                'id' => Uuid::uuid4()->getHex(),
                                'taxRate' => 99,
                                'active' => true,
                                'name' => 'required',
                                'customerGroupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
                            ],
                        ],
                ],
        ];

        TaxFixtures::$taxFixtureRepository = EntityFixturesBase::getFixtureRepository('tax');
    }

    public function getTaxNineteenPercent(): TaxStruct
    {
        return $this->getTaxFixture('NineteenPercentTax');
    }

    public function getTaxNineteenPercentWithAreaRule(): TaxStruct
    {
        return $this->getTaxFixture('NineteenPercentTaxWithAreaRule');
    }

    private function getTaxFixture(string $fixtureName): TaxStruct
    {
        return $this->createFixture($fixtureName, TaxFixtures::$taxFixtures, TaxFixtures::$taxFixtureRepository);
    }
}
