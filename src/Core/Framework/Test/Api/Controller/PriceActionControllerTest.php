<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Test\TaxFixtures;
use Symfony\Component\Serializer\Serializer;

class PriceActionControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;
    use TaxFixtures;

    /**
     * @var EntityRepositoryInterface
     */
    private $taxRepository;

    /**
     * @var Serializer
     */
    private $serializer;

    protected function setUp(): void
    {
        $this->taxRepository = $this->getContainer()->get('tax.repository');
        $this->serializer = $this->getContainer()->get('serializer');
    }

    public function testPriceMissingExecption(): void
    {
        $this->getBrowser()->request('POST', '/api/price/actions/calculate');

        $response = $this->getBrowser()->getResponse()->getContent();
        $response = json_decode($response, true);

        static::assertArrayHasKey('errors', $response);
    }

    public function testTaxIdMissingException(): void
    {
        $this->getBrowser()->request('POST', '/api/price/actions/calculate', ['price' => 10]);

        $response = $this->getBrowser()->getResponse()->getContent();

        $response = json_decode($response, true);

        static::assertArrayHasKey('errors', $response);
    }

    public function testTaxNotFoundException(): void
    {
        $this->getBrowser()->request('POST', '/api/price/actions/calculate', [
            'price' => 10,
            'taxId' => Uuid::randomHex(),
        ]);

        $response = $this->getBrowser()->getResponse()->getContent();

        $response = json_decode($response, true);

        static::assertArrayHasKey('errors', $response);
    }

    public function testNetToGross(): void
    {
        $price = $this->sendRequest([
            'price' => 10,
            'taxId' => $this->getTaxNineteenPercent()->getId(),
            'calculated' => false,
        ]);

        static::assertEquals(
            new CalculatedPrice(
                11.9,
                11.9,
                new CalculatedTaxCollection([
                    new CalculatedTax(1.9, 19, 11.9),
                ]),
                new TaxRuleCollection([
                    new TaxRule(19, 100),
                ])
            ),
            $price
        );
    }

    public function testNetToNet(): void
    {
        $price = $this->sendRequest([
            'price' => 10.002,
            'output' => 'net',
            'taxId' => $this->getTaxNineteenPercent()->getId(),
            'calculated' => false,
        ]);

        static::assertEquals(
            new CalculatedPrice(
                10.002,
                10.002,
                new CalculatedTaxCollection([
                    new CalculatedTax(1.9003800000000002, 19, 10.002),
                ]),
                new TaxRuleCollection([
                    new TaxRule(19, 100),
                ])
            ),
            $price
        );
    }

    public function testGrossToGross(): void
    {
        $price = $this->sendRequest([
            'price' => 11.9,
            'taxId' => $this->getTaxNineteenPercent()->getId(),
            'calculated' => true,
        ]);

        static::assertEquals(
            new CalculatedPrice(
                11.9,
                11.9,
                new CalculatedTaxCollection([
                    new CalculatedTax(1.9, 19, 11.9),
                ]),
                new TaxRuleCollection([
                    new TaxRule(19, 100),
                ])
            ),
            $price
        );
    }

    public function testNetToGrossWithQuantity(): void
    {
        $price = $this->sendRequest([
            'price' => 10,
            'quantity' => 2,
            'taxId' => $this->getTaxNineteenPercent()->getId(),
            'calculated' => false,
        ]);

        static::assertEquals(
            new CalculatedPrice(
                11.9,
                23.8,
                new CalculatedTaxCollection([
                    new CalculatedTax(3.8, 19, 23.8),
                ]),
                new TaxRuleCollection([
                    new TaxRule(19, 100),
                ]),
                2
            ),
            $price
        );
    }

    public function testGrossToGrossWithQuantity(): void
    {
        $price = $this->sendRequest([
            'price' => 10,
            'quantity' => 2,
            'taxId' => $this->getTaxNineteenPercent()->getId(),
            'calculated' => true,
        ]);

        static::assertEquals(
            new CalculatedPrice(
                10,
                20,
                new CalculatedTaxCollection([
                    new CalculatedTax(3.19327731092437, 19, 20),
                ]),
                new TaxRuleCollection([
                    new TaxRule(19, 100),
                ]),
                2
            ),
            $price
        );
    }

    public function testGrossToNet(): void
    {
        $price = $this->sendRequest([
            'price' => 11.9,
            'output' => 'net',
            'taxId' => $this->getTaxNineteenPercent()->getId(),
            'calculated' => true,
        ]);

        static::assertEquals(
            new CalculatedPrice(
                11.9,
                11.9,
                new CalculatedTaxCollection([
                    new CalculatedTax(2.261, 19, 11.9),
                ]),
                new TaxRuleCollection([
                    new TaxRule(19, 100),
                ])
            ),
            $price
        );
    }

    private function sendRequest(array $data): CalculatedPrice
    {
        $url = '/api/_action/calculate-price';
        $this->getBrowser()->request('POST', $url, $data);

        $response = $this->getBrowser()->getResponse()->getContent();

        $response = json_decode($response, true);

        static::assertArrayHasKey('data', $response);

        $data = $response['data'];

        return new CalculatedPrice(
            $data['unitPrice'],
            $data['totalPrice'],
            new CalculatedTaxCollection(
                array_map(function ($row) {
                    return new CalculatedTax($row['tax'], $row['taxRate'], $row['price']);
                }, $data['calculatedTaxes'])
            ),
            new TaxRuleCollection(array_map(function ($row) {
                return new TaxRule($row['taxRate'], $row['percentage']);
            }, $data['taxRules'])),
            $data['quantity']
        );
    }
}
