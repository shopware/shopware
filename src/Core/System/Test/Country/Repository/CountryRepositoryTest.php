<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\Country\Repository;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\Term\EntityScoreQueryBuilder;
use Shopware\Core\Framework\ORM\Search\Term\SearchTermInterpreter;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\System\Country\CountryDefinition;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CountryRepositoryTest extends KernelTestCase
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
        $this->repository = self::$container->get(\Shopware\Core\System\Country\CountryRepository::class);
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
        $recordA = Uuid::uuid4()->getHex();
        $recordB = Uuid::uuid4()->getHex();

        $records = [
            ['id' => $recordA, 'name' => 'match'],
            ['id' => $recordB, 'name' => 'not', 'iso' => 'match'],
        ];

        $this->repository->create($records, Context::createDefaultContext(Defaults::TENANT_ID));

        $criteria = new Criteria();

        $builder = self::$container->get(EntityScoreQueryBuilder::class);
        $pattern = self::$container->get(SearchTermInterpreter::class)->interpret('match', Context::createDefaultContext(Defaults::TENANT_ID));
        $queries = $builder->buildScoreQueries($pattern, CountryDefinition::class, CountryDefinition::getEntityName());
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
