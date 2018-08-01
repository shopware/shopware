<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Controller;

use Shopware\Core\Content\Product\ProductStruct;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\Read\ReadCriteria;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\Pricing\PriceStruct;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\Api\ApiTestCase;
use Shopware\Core\PlatformRequest;

class ProductActionControllerTest extends ApiTestCase
{
    /**
     * @var RepositoryInterface
     */
    private $productRepository;

    protected function setUp()
    {
        parent::setUp();

        $this->productRepository = $this->getContainer()->get('product.repository');
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
            'tax' => ['name' => 'test', 'taxRate' => 15],
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

        static::assertSame(204, $this->apiClient->getResponse()->getStatusCode());

        $criteria = new ReadCriteria([$id]);
        $criteria->addAssociation('product.configurators');
        $product = $this->productRepository->read($criteria, Context::createDefaultContext(Defaults::TENANT_ID))->get($id);

        /** @var ProductStruct $product */
        $configurators = $product->getConfigurators();

        static::assertCount(2, $configurators);

        static::assertTrue($configurators->has($redId));
        static::assertTrue($configurators->has($blueId));

        $blue = $configurators->get($blueId);
        $red = $configurators->get($redId);

        static::assertEquals(new PriceStruct(25, 50), $red->getPrice());
        static::assertEquals(new PriceStruct(90, 100), $blue->getPrice());

        static::assertEquals('red', $red->getOption()->getName());
        static::assertEquals('blue', $blue->getOption()->getName());

        static::assertEquals($colorId, $red->getOption()->getGroupId());
        static::assertEquals($colorId, $blue->getOption()->getGroupId());

        $this->apiClient->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/product/' . $id . '/actions/generate-variants');
        static::assertSame(200, $this->apiClient->getResponse()->getStatusCode());

        $ids = $this->apiClient->getResponse()->getContent();
        static::assertNotEmpty($ids);

        $ids = json_decode($ids, true);
        static::assertArrayHasKey('data', $ids);
        static::assertCount(2, $ids['data']);

        $products = $this->productRepository->read(new ReadCriteria($ids['data']), Context::createDefaultContext(
            Defaults::TENANT_ID));

        foreach ($products as $product) {
            static::assertSame($id, $product->getParentId());
        }
    }
}
