<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\ScheduledTask;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleCollection;
use Shopware\Core\Framework\App\ScheduledTask\DeleteCascadeAppsHandler;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\Integration\IntegrationCollection;
use Symfony\Component\Clock\NativeClock;

/**
 * @internal
 */
#[Package('framework')]
class DeleteCascadeAppsHandlerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    /**
     * @var EntityRepository<AclRoleCollection>
     */
    private EntityRepository $aclRoleRepo;

    /**
     * @var EntityRepository<IntegrationCollection>
     */
    private EntityRepository $integrationRepo;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->aclRoleRepo = static::getContainer()->get('acl_role.repository');
        $this->integrationRepo = static::getContainer()->get('integration.repository');
    }

    public function testCanDelete(): void
    {
        $timeExpired = (new \DateTimeImmutable())->modify('-1 day')->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->connection->executeStatement('DELETE FROM acl_role');
        $this->connection->executeStatement('DELETE FROM integration');

        $this->aclRoleRepo->create([
            [
                'name' => 'SwagApp',
                'deletedAt' => $timeExpired,
                'integrations' => [
                    [
                        'label' => 'test',
                        'accessKey' => 'api access key',
                        'secretAccessKey' => 'test',
                        'deletedAt' => $timeExpired,
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        $handler = new DeleteCascadeAppsHandler(
            static::getContainer()->get('scheduled_task.repository'),
            $this->createMock(LoggerInterface::class),
            $this->aclRoleRepo,
            $this->integrationRepo,
            new NativeClock(),
        );

        $handler->run();

        static::assertCount(0, $this->aclRoleRepo->search(new Criteria(), Context::createDefaultContext())->getEntities());
        static::assertCount(0, $this->integrationRepo->search(new Criteria(), Context::createDefaultContext())->getEntities());
    }
}
