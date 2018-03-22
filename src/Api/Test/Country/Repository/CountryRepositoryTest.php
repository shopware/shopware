<?php declare(strict_types=1);

namespace Shopware\Api\Test\Country\Repository;

use Doctrine\DBAL\Connection;
use Shopware\Api\Country\Definition\CountryDefinition;
use Shopware\Api\Country\Repository\CountryRepository;
use Shopware\Api\Entity\RepositoryInterface;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\Term\EntityScoreQueryBuilder;
use Shopware\Api\Entity\Search\Term\SearchTermInterpreter;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Struct\Uuid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CountryRepositoryTest extends KernelTestCase
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var RepositoryInterface
     */
    private $repository;

    public function setUp()
    {
        self::bootKernel();
        $this->container = self::$kernel->getContainer();
        $this->repository = $this->container->get(CountryRepository::class);
        $this->connection = $this->container->get(Connection::class);
        $this->connection->beginTransaction();
    }

    public function testSearchRanking()
    {
        $recordA = Uuid::uuid4()->getHex();
        $recordB = Uuid::uuid4()->getHex();

        $records = [
            ['id' => $recordA, 'name' => 'match'],
            ['id' => $recordB, 'name' => 'not', 'iso' => 'match'],
        ];

        $this->repository->create($records, ShopContext::createDefaultContext());

        $criteria = new Criteria();

        $builder = $this->container->get(EntityScoreQueryBuilder::class);
        $pattern = $this->container->get(SearchTermInterpreter::class)->interpret('match', ShopContext::createDefaultContext());
        $queries = $builder->buildScoreQueries($pattern, CountryDefinition::class, CountryDefinition::getEntityName());
        $criteria->addQueries($queries);

        $result = $this->repository->searchIds($criteria, ShopContext::createDefaultContext());

        $this->assertCount(2, $result->getIds());

        $this->assertEquals(
            [$recordA, $recordB],
            $result->getIds()
        );

        $this->assertTrue(
            $result->getDataFieldOfId($recordA, 'score')
            >
            $result->getDataFieldOfId($recordB, 'score')
        );
    }
}
