<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\Repository;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class ProductSearchKeywordTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository
     */
    private $repository;

    private Context $context;

    protected function setUp(): void
    {
        $this->repository = $this->getContainer()->get('product.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testAddProductWithSearchKeyword(): void
    {
        $id = Uuid::randomHex();

        $this->createProduct($id, ['YTN', 'Search Keyword']);

        /** @var ProductEntity $product */
        $product = $this->repository
            ->search(new Criteria([$id]), $this->context)
            ->get($id);

        $customSearchKeywords = $product->getCustomSearchKeywords();
        static::assertIsArray($customSearchKeywords);
        static::assertContains('YTN', $customSearchKeywords);
        static::assertContains('Search Keyword', $customSearchKeywords);
    }

    public function testEditProductWithSearchKeyword(): void
    {
        $id = Uuid::randomHex();

        $this->createProduct($id, ['YTN']);

        /** @var ProductEntity $product */
        $product = $this->repository
            ->search(new Criteria([$id]), $this->context)
            ->get($id);

        $customSearchKeywords = $product->getCustomSearchKeywords();
        static::assertIsArray($customSearchKeywords);
        static::assertContains('YTN', $customSearchKeywords);

        $update = [
            'id' => $id,
            'customSearchKeywords' => ['YTN', 'Search Keyword Update'],
        ];

        $this->repository->update([$update], $this->context);

        /** @var ProductEntity $product */
        $product = $this->repository
            ->search(new Criteria([$id]), $this->context)
            ->get($id);

        $customSearchKeywords = $product->getCustomSearchKeywords();
        static::assertIsArray($customSearchKeywords);
        static::assertContains('YTN', $customSearchKeywords);
        static::assertContains('Search Keyword Update', $customSearchKeywords);
    }

    /**
     * @param array<string> $searchKeyword
     */
    private function createProduct(string $id, array $searchKeyword): void
    {
        $data = [
            'id' => $id,
            'name' => 'test',
            'productNumber' => Uuid::randomHex(),
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'manufacturer' => ['id' => '98432def39fc4624b33213a56b8c944f', 'name' => 'test'],
            'tax' => ['id' => '98432def39fc4624b33213a56b8c944f', 'name' => 'test', 'taxRate' => 15],
            'customSearchKeywords' => $searchKeyword,
        ];

        $this->repository->create([$data], $this->context);
    }
}
