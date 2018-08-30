<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\Integration;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\Read\ReadCriteria;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class IntegrationRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

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
        $this->repository = $this->getContainer()->get('integration.repository');
        $this->connection = $this->getContainer()->get(Connection::class);
    }

    public function testCreationWithAccessKeys()
    {
        $id = Uuid::uuid4()->getHex();

        $records = [
            [
                'id' => $id,
                'label' => 'My app',
                'accessKey' => AccessKeyHelper::generateAccessKey('integration'),
                'secretAccessKey' => AccessKeyHelper::generateSecretAccessKey(),
            ],
        ];

        $context = Context::createDefaultContext(Defaults::TENANT_ID);

        $this->repository->create($records, $context);

        $entities = $this->repository->read(new ReadCriteria([$id]), $context);

        static::assertEquals(1, $entities->count());
        static::assertEquals('My app', $entities->first()->getLabel());
    }
}
