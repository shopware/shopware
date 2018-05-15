<?php declare(strict_types=1);

namespace Shopware\Api\Test\Country\Repository;

use Doctrine\DBAL\Connection;
use Shopware\System\Country\Definition\CountryStateDefinition;
use Shopware\System\Country\Repository\CountryRepository;
use Shopware\System\Country\Repository\CountryStateRepository;
use Shopware\Framework\ORM\RepositoryInterface;
use Shopware\Framework\ORM\Search\Criteria;
use Shopware\Framework\ORM\Search\Term\EntityScoreQueryBuilder;
use Shopware\Framework\ORM\Search\Term\SearchTermInterpreter;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Defaults;
use Shopware\Framework\Struct\Uuid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CountryStateRepositoryTest extends KernelTestCase
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
        $this->repository = $this->container->get(CountryStateRepository::class);
        $this->connection = $this->container->get(Connection::class);
        $this->connection->beginTransaction();
    }

    protected function tearDown()
    {
        $this->connection->rollBack();
        parent::tearDown();
    }

    public function testSearchRanking()
    {
        $country = Uuid::uuid4()->getHex();

        $this->container->get(CountryRepository::class)->create([
            ['id' => $country, 'name' => 'test'],
        ], ApplicationContext::createDefaultContext(Defaults::TENANT_ID));

        $recordA = Uuid::uuid4()->getHex();
        $recordB = Uuid::uuid4()->getHex();

        $records = [
            ['id' => $recordA, 'name' => 'match', 'shortCode' => 'test',    'countryId' => $country],
            ['id' => $recordB, 'name' => 'not',   'shortCode' => 'match 1', 'countryId' => $country],
        ];

        $this->repository->create($records, ApplicationContext::createDefaultContext(Defaults::TENANT_ID));

        $criteria = new Criteria();

        $builder = $this->container->get(EntityScoreQueryBuilder::class);
        $pattern = $this->container->get(SearchTermInterpreter::class)->interpret('match', ApplicationContext::createDefaultContext(Defaults::TENANT_ID));
        $queries = $builder->buildScoreQueries($pattern, CountryStateDefinition::class, CountryStateDefinition::getEntityName());
        $criteria->addQueries($queries);

        $result = $this->repository->searchIds($criteria, ApplicationContext::createDefaultContext(Defaults::TENANT_ID));

        $this->assertCount(2, $result->getIds());

        $this->assertEquals(
            [$recordA, $recordB],
            $result->getIds()
        );

        $this->assertTrue(
            $result->getDataFieldOfId($recordA, '_score')
            >
            $result->getDataFieldOfId($recordB, '_score')
        );
    }
}
