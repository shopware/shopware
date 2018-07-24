<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\Repository;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\Term\EntityScoreQueryBuilder;
use Shopware\Core\Framework\ORM\Search\Term\SearchPattern;
use Shopware\Core\Framework\ORM\Search\Term\SearchTerm;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Struct\Uuid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ProductSearchScoringTest extends KernelTestCase
{
    /** @var Connection */
    private $connection;

    /** @var RepositoryInterface */
    private $repository;

    protected function setUp()
    {
        parent::setUp();
        self::bootKernel();
        $this->connection = self::$container->get(Connection::class);
        $this->repository = self::$container->get('product.repository');
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
        $pattern = new SearchPattern(new SearchTerm('test'), 'product');
        $builder = new EntityScoreQueryBuilder();
        $queries = $builder->buildScoreQueries($pattern, ProductDefinition::class, ProductDefinition::getEntityName());

        $criteria = new Criteria();
        $criteria->addQueries($queries);

        $context = Context::createDefaultContext(Defaults::TENANT_ID);
        $this->repository->create([
            ['id' => Uuid::uuid4()->getHex(), 'name' => 'product 1 test', 'tax' => ['name' => 'test', 'taxRate' => 5], 'manufacturer' => ['name' => 'test'], 'price' => ['gross' => 10, 'net' => 9]],
            ['id' => Uuid::uuid4()->getHex(), 'name' => 'product 2 test', 'tax' => ['name' => 'test', 'taxRate' => 5], 'manufacturer' => ['name' => 'test'], 'price' => ['gross' => 10, 'net' => 9]],
        ], $context);

        $result = $this->repository->search($criteria, $context);

        /** @var Entity $entity */
        foreach ($result as $entity) {
            $this->assertArrayHasKey('search', $entity->getExtensions());
            /** @var ArrayStruct $extension */
            $extension = $entity->getExtension('search');

            $this->assertInstanceOf(ArrayStruct::class, $extension);
            $this->assertArrayHasKey('score', $extension);
            $this->assertGreaterThan(0, (float) $extension['score']);
        }
    }
}
