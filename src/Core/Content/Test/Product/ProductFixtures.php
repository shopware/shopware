<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product;

use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Test\EntityFixturesBase;

trait ProductFixtures
{
    use EntityFixturesBase;

    /**
     * @var array
     */
    public $productFixtures;

    /**
     * @before
     */
    public function initializeProductFixtures(): void
    {
        $this->productFixtures = [
            'MinimalProduct' => [
                'id' => Uuid::randomHex(),
                'name' => 'test',
                'price' => ['gross' => 1.19, 'net' => 1.0, 'linked' => false],
                'manufacturer' => ['id' => Uuid::randomHex(), 'name' => 'test'],
                'tax' => ['id' => Uuid::randomHex(), 'taxRate' => 19, 'name' => 'with id'],
                'stock' => 0,
            ],
        ];
    }

    public function getMinimalProduct(array $overrideData = []): ProductEntity
    {
        return $this->getProductFixture('MinimalProduct', $overrideData);
    }

    private function getProductFixture(string $fixtureName, array $overrideData): ProductEntity
    {
        return $this->createFixture(
            $fixtureName,
            $this->productFixtures,
            EntityFixturesBase::getFixtureRepository('product'),
            $overrideData
        );
    }
}
