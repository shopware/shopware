<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\Repository;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\EntityScoreQueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\SearchPattern;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\SearchTerm;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class ProductSearchScoringTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EntityRepository
     */
    private $repository;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->repository = $this->getContainer()->get('product.repository');
    }

    public function testScoringExtensionExists(): void
    {
        $context = Context::createDefaultContext();
        $pattern = new SearchPattern(new SearchTerm('test'));
        $builder = new EntityScoreQueryBuilder();
        $queries = $builder->buildScoreQueries(
            $pattern,
            $this->getContainer()->get(ProductDefinition::class),
            $this->getContainer()->get(ProductDefinition::class)->getEntityName(),
            $context
        );

        $criteria = new Criteria();
        $criteria->addQuery(...$queries);

        $this->repository->create([
            ['id' => Uuid::randomHex(), 'productNumber' => Uuid::randomHex(), 'stock' => 10, 'name' => 'product 1 test', 'tax' => ['name' => 'test', 'taxRate' => 5], 'manufacturer' => ['name' => 'test'], 'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]]],
            ['id' => Uuid::randomHex(), 'productNumber' => Uuid::randomHex(), 'stock' => 10, 'name' => 'product 2 test', 'tax' => ['name' => 'test', 'taxRate' => 5], 'manufacturer' => ['name' => 'test'], 'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]]],
        ], $context);

        $result = $this->repository->search($criteria, $context);

        /** @var Entity $entity */
        foreach ($result as $entity) {
            static::assertArrayHasKey('search', $entity->getExtensions());
            /** @var ArrayEntity $extension */
            $extension = $entity->getExtension('search');

            static::assertInstanceOf(ArrayEntity::class, $extension);
            static::assertArrayHasKey('_score', $extension);
            static::assertGreaterThan(0, (float) $extension['_score']);
        }
    }
}
