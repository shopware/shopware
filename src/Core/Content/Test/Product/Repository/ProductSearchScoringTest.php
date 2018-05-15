<?php declare(strict_types=1);

namespace Shopware\Content\Test\Product\Repository;

use Doctrine\DBAL\Connection;
use Shopware\Framework\ORM\Entity;
use Shopware\Framework\ORM\Search\Criteria;
use Shopware\Framework\ORM\Search\Term\EntityScoreQueryBuilder;
use Shopware\Framework\ORM\Search\Term\SearchPattern;
use Shopware\Framework\ORM\Search\Term\SearchTerm;
use Shopware\Content\Product\Definition\ProductDefinition;
use Shopware\Content\Product\Repository\ProductRepository;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Struct\ArrayStruct;
use Shopware\Framework\Struct\Uuid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ProductSearchScoringTest extends KernelTestCase
{
    /** @var Connection */
    private $connection;

    /** @var ProductRepository */
    private $repository;

    protected function setUp()
    {
        parent::setUp();
        $kernel = self::bootKernel();
        $this->connection = $kernel->getContainer()->get(Connection::class);
        $this->repository = $kernel->getContainer()->get(ProductRepository::class);
        $this->connection->beginTransaction();
        $this->connection->executeUpdate('DELETE FROM product');
    }

    protected function tearDown()
    {
        $this->connection->rollBack();
        parent::tearDown();
    }

    public function testScoringExtensionExists()
    {
        $pattern = new SearchPattern(new SearchTerm('test'));
        $builder = new EntityScoreQueryBuilder();
        $queries = $builder->buildScoreQueries($pattern, ProductDefinition::class, ProductDefinition::getEntityName());

        $criteria = new Criteria();
        $criteria->addQueries($queries);

        $context = ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID);
        $this->repository->create([
            ['id' => Uuid::uuid4()->getHex(), 'name' => 'product 1 test', 'tax' => ['name' => 'test', 'rate' => 5], 'manufacturer' => ['name' => 'test'], 'price' => ['gross' => 10, 'net' => 9]],
            ['id' => Uuid::uuid4()->getHex(), 'name' => 'product 2 test', 'tax' => ['name' => 'test', 'rate' => 5], 'manufacturer' => ['name' => 'test'], 'price' => ['gross' => 10, 'net' => 9]],
        ], $context);

        $result = $this->repository->search($criteria, $context);

        /** @var Entity $entity */
        foreach ($result as $entity) {
            $this->assertArrayHasKey('search', $entity->getExtensions());
            /** @var ArrayStruct $extension */
            $extension = $entity->getExtension('search');

            $this->assertInstanceOf(ArrayStruct::class, $extension);
            $this->assertArrayHasKey('_score', $extension);
            $this->assertGreaterThan(0, (float) $extension['_score']);
        }
    }
}
