<?php

namespace Shopware\Api\Test\Search;

use Doctrine\DBAL\Connection;
use Shopware\Api\Entity\Entity;
use Shopware\Api\Search\Criteria;
use Shopware\Api\Search\Term\EntityScoreQueryBuilder;
use Shopware\Api\Search\Term\SearchPattern;
use Shopware\Api\Search\Term\SearchTerm;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\ArrayStruct;
use Shopware\Product\Definition\ProductDefinition;
use Shopware\Product\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class SearchScoringTest extends KernelTestCase
{
    /** @var Connection */
    private $connection;

    /** @var ProductRepository */
    private $repository;

    protected function setUp()
    {
        parent::setUp();
        $kernel = self::bootKernel();
        $this->connection = $kernel->getContainer()->get('dbal_connection');
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
        foreach ($queries as $query) {
            $criteria->addQuery($query);
        }

        $context = new TranslationContext('SWAG-SHOP-UUID-1', true, null);
        $this->repository->create([
            ['uuid' => 'product-1', 'name' => 'product 1 test'],
            ['uuid' => 'product-2', 'name' => 'product 2 test']
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