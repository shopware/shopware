<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Controller;

use Shopware\Core\Framework\Context;
use Shopware\Core\Content\Product\ProductRepository;
use Shopware\Core\Content\Product\Struct\PriceStruct;
use Shopware\Core\Content\Product\Struct\ProductDetailStruct;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\Api\ApiTestCase;
use Shopware\Core\PlatformRequest;

class ProductActionControllerTest extends ApiTestCase
{
    /**
     * @var ProductRepository
     */
    private $productRepository;

    protected function setUp()
    {
        parent::setUp();

        $this->productRepository = $this->getContainer()->get(ProductRepository::class);
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

        $this->apiClient->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/product', [], [], [], json_encode($data));

        $this->assertSame(204, $this->apiClient->getResponse()->getStatusCode());

        $product = $this->productRepository->readDetail([$id], Context::createDefaultContext(\Shopware\Core\Defaults::TENANT_ID))
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

        $this->apiClient->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/product/' . $id . '/actions/generate-variants');
        $this->assertSame(200, $this->apiClient->getResponse()->getStatusCode());

        $ids = $this->apiClient->getResponse()->getContent();
        $this->assertNotEmpty($ids);

        $ids = json_decode($ids, true);
        $this->assertArrayHasKey('data', $ids);
        $this->assertCount(2, $ids['data']);

        $products = $this->productRepository->readBasic($ids['data'], Context::createDefaultContext(
            \Shopware\Core\Defaults::TENANT_ID));

        foreach ($products as $product) {
            $this->assertSame($id, $product->getParentId());
        }
    }
}
