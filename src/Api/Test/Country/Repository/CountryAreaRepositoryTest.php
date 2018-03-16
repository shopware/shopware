<?php declare(strict_types=1);

namespace Shopware\Api\Test\Country\Repository;

use Doctrine\DBAL\Connection;
use Shopware\Framework\Struct\Uuid;
use Shopware\Api\Country\Definition\CountryAreaDefinition;
use Shopware\Api\Country\Repository\CountryAreaRepository;
use Shopware\Api\Entity\RepositoryInterface;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\Term\EntityScoreQueryBuilder;
use Shopware\Api\Entity\Search\Term\SearchTermInterpreter;
use Shopware\Context\Struct\ShopContext;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CountryAreaRepositoryTest extends KernelTestCase
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
        $this->repository = $this->container->get(CountryAreaRepository::class);
        $this->connection = $this->container->get(Connection::class);
        $this->connection->beginTransaction();
    }

    public function testSearchRanking()
    {
        $recordA = Uuid::uuid4()->getHex();
        $recordB = Uuid::uuid4()->getHex();

        $records = [
            ['id' => $recordA, 'name' => 'match'],
            ['id' => $recordB, 'name' => 'match not exact'],
        ];

        $this->repository->create($records, ShopContext::createDefaultContext());

        $criteria = new Criteria();

        $builder = $this->container->get(EntityScoreQueryBuilder::class);
        $pattern = $this->container->get(SearchTermInterpreter::class)->interpret('match', ShopContext::createDefaultContext());
        $queries = $builder->buildScoreQueries($pattern, CountryAreaDefinition::class, CountryAreaDefinition::getEntityName());
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
