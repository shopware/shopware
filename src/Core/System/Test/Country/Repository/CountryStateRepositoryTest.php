<?php declare(strict_types=1);

namespace Shopware\System\Test\Country\Repository;

use Doctrine\DBAL\Connection;
use Shopware\Framework\Context;
use Shopware\Defaults;
use Shopware\Framework\ORM\RepositoryInterface;
use Shopware\Framework\ORM\Search\Criteria;
use Shopware\Framework\ORM\Search\Term\EntityScoreQueryBuilder;
use Shopware\Framework\ORM\Search\Term\SearchTermInterpreter;
use Shopware\Framework\Struct\Uuid;
use Shopware\System\Country\Aggregate\CountryState\CountryStateDefinition;
use Shopware\System\Country\Aggregate\CountryState\CountryStateRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CountryStateRepositoryTest extends KernelTestCase
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var RepositoryInterface
     */
    private $repository;

    public function setUp()
    {
        self::bootKernel();
        $this->repository = self::$container->get(CountryStateRepository::class);
        $this->connection = self::$container->get(Connection::class);
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

        self::$container->get(\Shopware\System\Country\CountryRepository::class)->create([
            ['id' => $country, 'name' => 'test'],
        ], Context::createDefaultContext(Defaults::TENANT_ID));

        $recordA = Uuid::uuid4()->getHex();
        $recordB = Uuid::uuid4()->getHex();

        $records = [
            ['id' => $recordA, 'name' => 'match', 'shortCode' => 'test',    'countryId' => $country],
            ['id' => $recordB, 'name' => 'not',   'shortCode' => 'match 1', 'countryId' => $country],
        ];

        $this->repository->create($records, Context::createDefaultContext(Defaults::TENANT_ID));

        $criteria = new Criteria();

        $builder = self::$container->get(EntityScoreQueryBuilder::class);
        $pattern = self::$container->get(SearchTermInterpreter::class)->interpret('match', Context::createDefaultContext(Defaults::TENANT_ID));
        $queries = $builder->buildScoreQueries($pattern, CountryStateDefinition::class, CountryStateDefinition::getEntityName());
        $criteria->addQueries($queries);

        $result = $this->repository->searchIds($criteria, Context::createDefaultContext(Defaults::TENANT_ID));

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
