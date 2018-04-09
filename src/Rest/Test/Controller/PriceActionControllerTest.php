<?php

namespace Shopware\Rest\Test\Controller;

use Shopware\Api\Tax\Repository\TaxRepository;
use Shopware\Cart\Price\Struct\CalculatedPrice;
use Shopware\Cart\Tax\Struct\CalculatedTax;
use Shopware\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Cart\Tax\Struct\PercentageTaxRule;
use Shopware\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Struct\Uuid;
use Shopware\Rest\Test\ApiTestCase;
use Symfony\Component\BrowserKit\Client;
use Symfony\Component\Serializer\Serializer;

class PriceActionControllerTest extends ApiTestCase
{
    /**
     * @var TaxRepository
     */
    private $repository;

    /**
     * @var Serializer
     */
    private $serializer;

    protected function setUp()
    {
        parent::setUp();
        self::bootKernel();
        $container = self::$kernel->getContainer();
        $this->repository = $container->get(TaxRepository::class);
        $this->serializer = $container->get('serializer');
    }

    public function testPriceMissingExecption()
    {
        /** @var Client $client */
        $client = $this->getClient();
        $client->request('POST', '/api/price/actions/calculate');
        
        $response = $client->getResponse()->getContent();

        $response = json_decode($response, true);

        $this->assertArrayHasKey('errors', $response);
    }


    public function testTaxIdMissingException()
    {
        /** @var Client $client */
        $client = $this->getClient();
        $client->request('POST', '/api/price/actions/calculate', [], [], [], json_encode([
            'price' => 10
        ]));

        $response = $client->getResponse()->getContent();

        $response = json_decode($response, true);

        $this->assertArrayHasKey('errors', $response);
    }

    public function testTaxNotFoundException()
    {
        /** @var Client $client */
        $client = $this->getClient();
        $client->request('POST', '/api/price/actions/calculate', [], [], [], json_encode([
            'price' => 10,
            'taxId' => Uuid::uuid4()->getHex()
        ]));

        $response = $client->getResponse()->getContent();

        $response = json_decode($response, true);

        $this->assertArrayHasKey('errors', $response);
    }

    public function testNetToGross()
    {
        $taxId = Uuid::uuid4()->getHex();
        $this->repository->create([
            ['id' => $taxId, 'rate' => 19, 'name' => 'test']
        ], ShopContext::createDefaultContext());

        $price = $this->sendRequest([
            'price' => 10,
            'taxId' => $taxId,
            'calculated' => false
        ]);

        $this->assertEquals(
            new CalculatedPrice(
                11.9,
                11.9,
                new CalculatedTaxCollection([
                    new CalculatedTax(1.9, 19, 11.9)
                ]),
                new TaxRuleCollection([
                    new PercentageTaxRule(19, 100)
                ])
            ),
            $price
        );
    }

    public function testNetToNet()
    {
        $taxId = Uuid::uuid4()->getHex();
        $this->repository->create([
            ['id' => $taxId, 'rate' => 19, 'name' => 'test']
        ], ShopContext::createDefaultContext());

        $price = $this->sendRequest([
            'price' => 10.002,
            'output' => 'net',
            'taxId' => $taxId,
            'calculated' => false
        ]);

        $this->assertEquals(
            new CalculatedPrice(
                10,
                10,
                new CalculatedTaxCollection([
                    new CalculatedTax(1.9, 19, 10.0)
                ]),
                new TaxRuleCollection([
                    new PercentageTaxRule(19, 100)
                ])
            ),
            $price
        );
    }

    public function testGrossToGross()
    {
        $taxId = Uuid::uuid4()->getHex();
        $this->repository->create([
            ['id' => $taxId, 'rate' => 19, 'name' => 'test']
        ], ShopContext::createDefaultContext());

        $price = $this->sendRequest([
            'price' => 11.9,
            'taxId' => $taxId,
            'calculated' => true
        ]);

        $this->assertEquals(
            new CalculatedPrice(
                11.9,
                11.9,
                new CalculatedTaxCollection([
                    new CalculatedTax(1.9, 19, 11.9)
                ]),
                new TaxRuleCollection([
                    new PercentageTaxRule(19, 100)
                ])
            ),
            $price
        );
    }

    public function testNetToGrossWithQuantity()
    {
        $taxId = Uuid::uuid4()->getHex();
        $this->repository->create([
            ['id' => $taxId, 'rate' => 19, 'name' => 'test']
        ], ShopContext::createDefaultContext());

        $price = $this->sendRequest([
            'price' => 10,
            'quantity' => 2,
            'taxId' => $taxId,
            'calculated' => false
        ]);

        $this->assertEquals(
            new CalculatedPrice(
                11.9,
                23.8,
                new CalculatedTaxCollection([
                    new CalculatedTax(3.8, 19, 23.8)
                ]),
                new TaxRuleCollection([
                    new PercentageTaxRule(19, 100)
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
            ['id' => $taxId, 'rate' => 19, 'name' => 'test']
        ], ShopContext::createDefaultContext());

        $price = $this->sendRequest([
            'price' => 10,
            'quantity' => 2,
            'taxId' => $taxId,
            'calculated' => true
        ]);

        $this->assertEquals(
            new CalculatedPrice(
                10,
                20,
                new CalculatedTaxCollection([
                    new CalculatedTax(3.19, 19, 20)
                ]),
                new TaxRuleCollection([
                    new PercentageTaxRule(19, 100)
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
            ['id' => $taxId, 'rate' => 19, 'name' => 'test']
        ], ShopContext::createDefaultContext());

        $price = $this->sendRequest([
            'price' => 11.9,
            'output' => 'net',
            'taxId' => $taxId,
            'calculated' => true
        ]);

        $this->assertEquals(
            new CalculatedPrice(
                11.9,
                11.9,
                new CalculatedTaxCollection([
                    new CalculatedTax(2.26, 19, 11.9)
                ]),
                new TaxRuleCollection([
                    new PercentageTaxRule(19, 100)
                ])
            ),
            $price
        );
    }

    private function sendRequest(array $data): CalculatedPrice
    {
        /** @var Client $client */
        $client = $this->getClient();
        $client->request('POST', '/api/price/actions/calculate', [], [], [], json_encode($data));

        $response = $client->getResponse()->getContent();

        $response = json_decode($response, true);

        $this->assertArrayHasKey('data', $response);

        $data = $response['data'];

        echo '<pre>';
        print_r(json_encode($data, JSON_PRESERVE_ZERO_FRACTION));
        exit();

        return new CalculatedPrice(
            $data['unitPrice'],
            $data['totalPrice'],
            new CalculatedTaxCollection(
                array_map(function($row) {
                    return new CalculatedTax($row['tax'], $row['taxRate'], $row['price']);
                },$data['calculatedTaxes'])
            ),
            new TaxRuleCollection(array_map(function($row) {
                return new PercentageTaxRule($row['rate'], $row['percentage']);
            }, $data['taxRules'])),
            $data['quantity']
        );
    }

}