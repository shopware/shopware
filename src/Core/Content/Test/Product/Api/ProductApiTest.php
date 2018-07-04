<?php
declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\Api;

use Shopware\Core\Content\Product\Aggregate\ProductPriceRule\ProductPriceRuleStruct;
use Shopware\Core\Content\Product\ProductStruct;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\Read\ReadCriteria;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\Pricing\PriceStruct;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\Api\ApiTestCase;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Response;

class ProductApiTest extends ApiTestCase
{
    /**
     * @var RepositoryInterface
     */
    private $repository;

    protected function setUp()
    {
        self::bootKernel();
        parent::setUp();
        $this->repository = self::$container->get('product.repository');
    }

    public function tearDown()
    {
        parent::tearDown();
    }

    public function testModifyProductPriceMatrixOverApi(): void
    {
        $ruleA = Uuid::uuid4()->getHex();
        $ruleB = Uuid::uuid4()->getHex();

        self::$container->get('rule.repository')->create([
            ['id' => $ruleA, 'name' => 'test', 'payload' => new AndRule(), 'priority' => 1],
            ['id' => $ruleB, 'name' => 'test', 'payload' => new AndRule(), 'priority' => 2],
        ], Context::createDefaultContext(Defaults::TENANT_ID));

        $id = Uuid::uuid4()->getHex();

        $data = [
            'id' => $id,
            'name' => 'price test',
            'price' => ['gross' => 15, 'net' => 10],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'rate' => 15],
            'priceRules' => [
                [
                    'id' => $id,
                    'currencyId' => Defaults::CURRENCY,
                    'quantityStart' => 1,
                    'ruleId' => $ruleA,
                    'price' => ['gross' => 100, 'net' => 100],
                ],
            ],
        ];

        $this->apiClient->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/product', [], [], [], json_encode($data));
        $this->assertSame(Response::HTTP_NO_CONTENT, $this->apiClient->getResponse()->getStatusCode(), $this->apiClient->getResponse()->getContent());

        $context = Context::createDefaultContext(Defaults::TENANT_ID);
        $products = $this->repository->read(new ReadCriteria([$id]), $context);
        $this->assertTrue($products->has($id));

        /** @var ProductStruct $product */
        $product = $products->get($id);

        $this->assertCount(1, $product->getPriceRules());

        /** @var ProductPriceRuleStruct $price */
        $price = $product->getPriceRules()->first();
        $this->assertEquals($ruleA, $price->getRuleId());

        $data = [
            'id' => $id,
            'priceRules' => [
                //update existing rule with new price and quantity end to add another graduation
                [
                    'id' => $id,
                    'quantityEnd' => 20,
                    'price' => ['gross' => 5000, 'net' => 4000],
                ],

                //add new graduation to existing rule
                [
                    'currencyId' => Defaults::CURRENCY,
                    'quantityStart' => 21,
                    'ruleId' => $ruleA,
                    'price' => ['gross' => 10, 'net' => 50],
                ],
            ],
        ];

        $this->apiClient->request('PATCH', '/api/v' . PlatformRequest::API_VERSION . '/product/' . $id, [], [], [], json_encode($data));
        $this->assertSame(Response::HTTP_NO_CONTENT, $this->apiClient->getResponse()->getStatusCode(), $this->apiClient->getResponse()->getContent());

        $products = $this->repository->read(new ReadCriteria([$id]), $context);
        $this->assertTrue($products->has($id));

        /** @var ProductStruct $product */
        $product = $products->get($id);

        $this->assertCount(2, $product->getPriceRules());

        /** @var ProductPriceRuleStruct $price */
        $price = $product->getPriceRules()->get($id);
        $this->assertEquals($ruleA, $price->getRuleId());
        $this->assertEquals(new PriceStruct(4000, 5000), $price->getPrice());

        $this->assertEquals(1, $price->getQuantityStart());
        $this->assertEquals(20, $price->getQuantityEnd());

        $id3 = Uuid::uuid4()->getHex();

        $data = [
            'id' => $id,
            'priceRules' => [
                [
                    'id' => $id3,
                    'currencyId' => Defaults::CURRENCY,
                    'quantityStart' => 1,
                    'ruleId' => $ruleB,
                    'price' => ['gross' => 50, 'net' => 50],
                ],
            ],
        ];

        $this->apiClient->request('PATCH', '/api/v' . PlatformRequest::API_VERSION . '/product/' . $id, [], [], [], json_encode($data));
        $this->assertSame(Response::HTTP_NO_CONTENT, $this->apiClient->getResponse()->getStatusCode(), $this->apiClient->getResponse()->getContent());

        $products = $this->repository->read(new ReadCriteria([$id]), $context);
        $this->assertTrue($products->has($id));

        /** @var ProductStruct $product */
        $product = $products->get($id);

        $this->assertCount(3, $product->getPriceRules());

        /** @var ProductPriceRuleStruct $price */
        $price = $product->getPriceRules()->get($id3);
        $this->assertEquals($ruleB, $price->getRuleId());
        $this->assertEquals(new PriceStruct(50, 50), $price->getPrice());

        $this->assertEquals(1, $price->getQuantityStart());
        $this->assertNull($price->getQuantityEnd());
    }
}
