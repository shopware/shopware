<?php declare(strict_types=1);

namespace Shopware\Api\Test\Product\Repository;

use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;
use Shopware\Api\Entity\Entity;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\Term\EntityScoreQueryBuilder;
use Shopware\Api\Entity\Search\Term\SearchPattern;
use Shopware\Api\Entity\Search\Term\SearchTerm;
use Shopware\Api\Product\Definition\ProductDefinition;
use Shopware\Api\Product\Repository\ProductRepository;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\ArrayStruct;
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

        $context = TranslationContext::createDefaultContext();
        $this->repository->create([
            ['id' => Uuid::uuid4()->toString(), 'name' => 'product 1 test', 'taxId' => '49260353-68e3-4d9f-a695-e017d7a231b9', 'manufacturer' => ['name' => 'test'], 'price' => 10],
            ['id' => Uuid::uuid4()->toString(), 'name' => 'product 2 test', 'taxId' => '49260353-68e3-4d9f-a695-e017d7a231b9', 'manufacturer' => ['name' => 'test'], 'price' => 10],
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
