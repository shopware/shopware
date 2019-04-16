<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Search;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Context\AdminApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Search\CompositeEntitySearcher;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class CompositeEntitySearcherTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $productRepository;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var CompositeEntitySearcher
     */
    private $search;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var string
     */
    private $userId;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);

        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->search = $this->getContainer()->get(CompositeEntitySearcher::class);

        $this->userId = Uuid::randomHex();

        $origin = new AdminApiSource($this->userId);
        $this->context = Context::createDefaultContext($origin);

        $repo = $this->getContainer()->get('user.repository');
        $repo->upsert([
            [
                'id' => $this->userId,
                'firstName' => 'test-user',
                'lastName' => 'test',
                'localeId' => $this->getLocaleIdOfSystemLanguage(),
                'username' => 'test-user',
                'email' => Uuid::randomHex() . '@example.com',
                'password' => 'shopware',
            ],
        ], $this->context);
    }

    public function testProductRanking(): void
    {
        $productId1 = Uuid::randomHex();
        $productId2 = Uuid::randomHex();

        $filterId = Uuid::randomHex();

        $this->productRepository->upsert([
            ['id' => $productId1, 'productNumber' => Uuid::randomHex(), 'stock' => 1, 'name' => "${filterId}_test ${filterId}_product 1", 'price' => ['gross' => 10, 'net' => 9, 'linked' => false], 'tax' => ['name' => 'test', 'taxRate' => 5], 'manufacturer' => ['name' => 'test']],
            ['id' => $productId2, 'productNumber' => Uuid::randomHex(), 'stock' => 1, 'name' => "${filterId}_test ${filterId}_product 2", 'price' => ['gross' => 10, 'net' => 9, 'linked' => false], 'tax' => ['name' => 'test', 'taxRate' => 5], 'manufacturer' => ['name' => 'test']],
            ['id' => Uuid::randomHex(), 'productNumber' => Uuid::randomHex(), 'stock' => 1, 'name' => 'notmatch', 'price' => ['gross' => 10, 'net' => 9, 'linked' => false], 'tax' => ['name' => 'notmatch', 'taxRate' => 5], 'manufacturer' => ['name' => 'notmatch']],
            ['id' => Uuid::randomHex(), 'productNumber' => Uuid::randomHex(), 'stock' => 1, 'name' => 'notmatch', 'price' => ['gross' => 10, 'net' => 9, 'linked' => false], 'tax' => ['name' => 'notmatch', 'taxRate' => 5], 'manufacturer' => ['name' => 'notmatch']],
        ], $this->context);

        $result = $this->search->search("${filterId}_test ${filterId}_product", 20, $this->context, $this->userId);

        $productResult = @current(array_filter($result, function ($item) {
            return $item['entity'] === ProductDefinition::getEntityName();
        }));

        static::assertNotEmpty($productResult);

        /** @var ProductCollection $products */
        $products = $productResult['entities']->getEntities();
        $first = $products->first();
        $last = $products->last();

        static::assertInstanceOf(ProductEntity::class, $first);
        static::assertInstanceOf(ProductEntity::class, $last);

        $firstScore = $first->getExtension('search')->get('_score');
        $secondScore = $last->getExtension('search')->get('_score');

        static::assertSame($secondScore, $firstScore);
    }
}
