<?php declare(strict_types=1);

namespace Shopware\Rest\Test\Controller;

use Shopware\Api\Product\Repository\ProductRepository;
use Shopware\Api\Product\Struct\PriceStruct;
use Shopware\Api\Product\Struct\ProductDetailStruct;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Struct\Uuid;
use Shopware\Rest\Test\ApiTestCase;
use Symfony\Component\BrowserKit\Client;

class ProductActionControllerTest extends ApiTestCase
{
    /**
     * @var ProductRepository
     */
    private $repository;

    protected function setUp()
    {
        parent::setUp();
        $container = self::$kernel->getContainer();
        $this->repository = $container->get(ProductRepository::class);
    }

    public function testGenerateVariant(): void
    {
        $id = Uuid::uuid4()->getHex();
        $redId = Uuid::uuid4()->getHex();
        $blueId = Uuid::uuid4()->getHex();
        $colorId = Uuid::uuid4()->getHex();

        $data = [
            'id' => $id,
            'name' => 'test',
            'tax' => ['name' => 'test', 'rate' => 15],
            'price' => ['gross' => 10, 'net' => 9],
            'manufacturer' => ['name' => 'test'],
            'configurators' => [
                [
                    'id' => $redId,
                    'price' => ['gross' => 50, 'net' => 25],
                    'option' => [
                        'id' => $redId,
                        'name' => 'red',
                        'group' => ['id' => $colorId, 'name' => $colorId],
                    ],
                ],
                [
                    'id' => $blueId,
                    'price' => ['gross' => 100, 'net' => 90],
                    'option' => [
                        'id' => $blueId,
                        'name' => 'blue',
                        'groupId' => $colorId,
                    ],
                ],
            ],
        ];

        /** @var Client $client */
        $client = $this->getClient();
        $client->request('POST', '/api/product', [], [], [], json_encode($data));

        $this->assertSame(204, $client->getResponse()->getStatusCode());

        $product = $this->repository->readDetail([$id], ShopContext::createDefaultContext())
            ->get($id);

        /** @var ProductDetailStruct $product */
        $configurators = $product->getConfigurators();

        $this->assertCount(2, $configurators);

        $this->assertTrue($configurators->has($redId));
        $this->assertTrue($configurators->has($blueId));

        $blue = $configurators->get($blueId);
        $red = $configurators->get($redId);

        $this->assertEquals(new PriceStruct(25, 50), $red->getPrice());
        $this->assertEquals(new PriceStruct(90, 100), $blue->getPrice());

        $this->assertEquals('red', $red->getOption()->getName());
        $this->assertEquals('blue', $blue->getOption()->getName());

        $this->assertEquals($colorId, $red->getOption()->getGroupId());
        $this->assertEquals($colorId, $blue->getOption()->getGroupId());

        /** @var Client $client */
        $client = $this->getClient();
        $client->request('POST', '/api/product/' . $id . '/actions/generate-variants');
        $this->assertSame(200, $client->getResponse()->getStatusCode());

        $ids = $client->getResponse()->getContent();
        $this->assertNotEmpty($ids);

        $ids = json_decode($ids, true);
        $this->assertArrayHasKey('data', $ids);
        $this->assertCount(2, $ids['data']);

        $products = $this->repository->readBasic($ids['data'], ShopContext::createDefaultContext());

        foreach ($products as $product) {
            $this->assertSame($id, $product->getParentId());
        }
    }
}
