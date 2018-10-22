<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\Price;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\PercentageTaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\Test\TaxFixtures;
use Symfony\Component\Serializer\Serializer;

class PriceActionControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour,TaxFixtures;

    /**
     * @var RepositoryInterface
     */
    private $taxRepository;

    /**
     * @var Serializer
     */
    private $serializer;

    protected function setUp()
    {
        $this->taxRepository = $this->getContainer()->get('tax.repository');
        $this->serializer = $this->getContainer()->get('serializer');
    }

    public function testPriceMissingExecption(): void
    {
        $this->getClient()->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/price/actions/calculate');

        $response = $this->getClient()->getResponse()->getContent();
        $response = json_decode($response, true);

        static::assertArrayHasKey('errors', $response);
    }

    public function testTaxIdMissingException(): void
    {
        $this->getClient()->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/price/actions/calculate', [], [], [], json_encode([
            'price' => 10,
        ]));

        $response = $this->getClient()->getResponse()->getContent();

        $response = json_decode($response, true);

        static::assertArrayHasKey('errors', $response);
    }

    public function testTaxNotFoundException(): void
    {
        $this->getClient()->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/price/actions/calculate', [], [], [], json_encode([
            'price' => 10,
            'taxId' => Uuid::uuid4()->getHex(),
        ]));

        $response = $this->getClient()->getResponse()->getContent();

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
            new Price(
                11.9,
                11.9,
                new CalculatedTaxCollection([
                    new CalculatedTax(1.9, 19, 11.9),
                ]),
                new TaxRuleCollection([
                    new PercentageTaxRule(19, 100),
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
            new Price(
                10,
                10,
                new CalculatedTaxCollection([
                    new CalculatedTax(1.9, 19, 10.0),
                ]),
                new TaxRuleCollection([
                    new PercentageTaxRule(19, 100),
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
            new Price(
                11.9,
                11.9,
                new CalculatedTaxCollection([
                    new CalculatedTax(1.9, 19, 11.9),
                ]),
                new TaxRuleCollection([
                    new PercentageTaxRule(19, 100),
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
            new Price(
                11.9,
                23.8,
                new CalculatedTaxCollection([
                    new CalculatedTax(3.8, 19, 23.8),
                ]),
                new TaxRuleCollection([
                    new PercentageTaxRule(19, 100),
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
            new Price(
                10,
                20,
                new CalculatedTaxCollection([
                    new CalculatedTax(3.19, 19, 20),
                ]),
                new TaxRuleCollection([
                    new PercentageTaxRule(19, 100),
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
            new Price(
                11.9,
                11.9,
                new CalculatedTaxCollection([
                    new CalculatedTax(2.26, 19, 11.9),
                ]),
                new TaxRuleCollection([
                    new PercentageTaxRule(19, 100),
                ])
            ),
            $price
        );
    }

    private function sendRequest(array $data): Price
    {
        $this->getClient()->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/price/actions/calculate', [], [], [], json_encode($data));

        $response = $this->getClient()->getResponse()->getContent();

        $response = json_decode($response, true);

        static::assertArrayHasKey('data', $response);

        $data = $response['data'];

        return new Price(
            $data['unitPrice'],
            $data['totalPrice'],
            new CalculatedTaxCollection(
                array_map(function ($row) {
                    return new CalculatedTax($row['tax'], $row['taxRate'], $row['price']);
                }, $data['calculatedTaxes'])
            ),
            new TaxRuleCollection(array_map(function ($row) {
                return new PercentageTaxRule($row['taxRate'], $row['percentage']);
            }, $data['taxRules'])),
            $data['quantity']
        );
    }
}
