<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Controller;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\PercentageTaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\Api\ApiTestCase;
use Shopware\Core\PlatformRequest;
use Symfony\Component\Serializer\Serializer;

class PriceActionControllerTest extends ApiTestCase
{
    /**
     * @var RepositoryInterface
     */
    private $repository;

    /**
     * @var Serializer
     */
    private $serializer;

    protected function setUp()
    {
        parent::setUp();

        $this->repository = $this->getContainer()->get('tax.repository');
        $this->serializer = $this->getContainer()->get('serializer');
    }

    public function testPriceMissingExecption()
    {
        $this->apiClient->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/price/actions/calculate');

        $response = $this->apiClient->getResponse()->getContent();
        $response = json_decode($response, true);

        $this->assertArrayHasKey('errors', $response);
    }

    public function testTaxIdMissingException()
    {
        $this->apiClient->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/price/actions/calculate', [], [], [], json_encode([
            'price' => 10,
        ]));

        $response = $this->apiClient->getResponse()->getContent();

        $response = json_decode($response, true);

        $this->assertArrayHasKey('errors', $response);
    }

    public function testTaxNotFoundException()
    {
        $this->apiClient->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/price/actions/calculate', [], [], [], json_encode([
            'price' => 10,
            'taxId' => Uuid::uuid4()->getHex(),
        ]));

        $response = $this->apiClient->getResponse()->getContent();

        $response = json_decode($response, true);

        $this->assertArrayHasKey('errors', $response);
    }

    public function testNetToGross()
    {
        $taxId = Uuid::uuid4()->getHex();
        $this->repository->create([
            ['id' => $taxId, 'rate' => 19, 'name' => 'test'],
        ], Context::createDefaultContext(\Shopware\Core\Defaults::TENANT_ID));

        $price = $this->sendRequest([
            'price' => 10,
            'taxId' => $taxId,
            'calculated' => false,
        ]);

        $this->assertEquals(
            new CalculatedPrice(
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

    public function testNetToNet()
    {
        $taxId = Uuid::uuid4()->getHex();
        $this->repository->create([
            ['id' => $taxId, 'rate' => 19, 'name' => 'test'],
        ], Context::createDefaultContext(\Shopware\Core\Defaults::TENANT_ID));

        $price = $this->sendRequest([
            'price' => 10.002,
            'output' => 'net',
            'taxId' => $taxId,
            'calculated' => false,
        ]);

        $this->assertEquals(
            new CalculatedPrice(
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

    public function testGrossToGross()
    {
        $taxId = Uuid::uuid4()->getHex();
        $this->repository->create([
            ['id' => $taxId, 'rate' => 19, 'name' => 'test'],
        ], Context::createDefaultContext(Defaults::TENANT_ID));

        $price = $this->sendRequest([
            'price' => 11.9,
            'taxId' => $taxId,
            'calculated' => true,
        ]);

        $this->assertEquals(
            new CalculatedPrice(
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

    public function testNetToGrossWithQuantity()
    {
        $taxId = Uuid::uuid4()->getHex();
        $this->repository->create([
            ['id' => $taxId, 'rate' => 19, 'name' => 'test'],
        ], Context::createDefaultContext(Defaults::TENANT_ID));

        $price = $this->sendRequest([
            'price' => 10,
            'quantity' => 2,
            'taxId' => $taxId,
            'calculated' => false,
        ]);

        $this->assertEquals(
            new CalculatedPrice(
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

    public function testGrossToGrossWithQuantity()
    {
        $taxId = Uuid::uuid4()->getHex();
        $this->repository->create([
            ['id' => $taxId, 'rate' => 19, 'name' => 'test'],
        ], Context::createDefaultContext(Defaults::TENANT_ID));

        $price = $this->sendRequest([
            'price' => 10,
            'quantity' => 2,
            'taxId' => $taxId,
            'calculated' => true,
        ]);

        $this->assertEquals(
            new CalculatedPrice(
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

    public function testGrossToNet()
    {
        $taxId = Uuid::uuid4()->getHex();
        $this->repository->create([
            ['id' => $taxId, 'rate' => 19, 'name' => 'test'],
        ], Context::createDefaultContext(Defaults::TENANT_ID));

        $price = $this->sendRequest([
            'price' => 11.9,
            'output' => 'net',
            'taxId' => $taxId,
            'calculated' => true,
        ]);

        $this->assertEquals(
            new CalculatedPrice(
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

    private function sendRequest(array $data): CalculatedPrice
    {
        $this->apiClient->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/price/actions/calculate', [], [], [], json_encode($data));

        $response = $this->apiClient->getResponse()->getContent();

        $response = json_decode($response, true);

        $this->assertArrayHasKey('data', $response);

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
                return new PercentageTaxRule($row['rate'], $row['percentage']);
            }, $data['taxRules'])),
            $data['quantity']
        );
    }
}
