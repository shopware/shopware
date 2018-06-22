<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product;

use Ramsey\Uuid\Uuid;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\Test\Api\ApiTestCase;

class ProductControllerTest extends ApiTestCase
{
    /**
     * @var RepositoryInterface
     */
    private $productRepository;

    protected function setUp()
    {
        parent::setUp();

        $this->productRepository = self::$container->get('product.repository');
    }

    public function testProductList()
    {
        $manufacturerId = Uuid::uuid4()->toString();
        $taxId = Uuid::uuid4()->toString();

        $this->productRepository->create([
            [
                'id' => Uuid::uuid4()->toString(),
                'name' => 'Test',
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['id' => $manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $taxId, 'rate' => 17, 'name' => 'with id'],
            ],
        ], Context::createDefaultContext(Defaults::TENANT_ID));

        $this->storefrontApiClient->request('GET', '/storefront-api/product');

        self::assertSame(200, $this->storefrontApiClient->getResponse()->getStatusCode(), $this->storefrontApiClient->getResponse()->getContent());

        $content = json_decode($this->storefrontApiClient->getResponse()->getContent(), true);

        $this->assertNotEmpty($content);
        $this->assertArrayHasKey('total', $content);
        $this->assertArrayHasKey('data', $content);
        $this->assertGreaterThan(0, $content['total']);
        $this->assertNotEmpty($content['data']);

        foreach ($content['data'] as $product) {
            $this->assertArrayHasKey('calculatedListingPrice', $product);
            $this->assertArrayHasKey('calculatedPriceRules', $product);
            $this->assertArrayHasKey('calculatedPrice', $product);
            $this->assertArrayHasKey('price', $product);
            $this->assertArrayHasKey('name', $product);
            $this->assertArrayHasKey('id', $product);
        }
    }
}
