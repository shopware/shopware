<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\ScheduledTask;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\ScheduledTask\DeleteCascadeAppsHandler;
use Shopware\Core\Framework\App\ScheduledTask\DeleteCascadeAppsTask;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Group('skip-paratest')]
class DeleteCascadeAppsHandlerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private EntityRepository $scheduledTaskRepo;

    private EntityRepository $aclRoleRepo;

    private EntityRepository $integrationRepo;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->scheduledTaskRepo = $this->getContainer()->get('scheduled_task.repository');
        $this->aclRoleRepo = $this->getContainer()->get('acl_role.repository');
        $this->integrationRepo = $this->getContainer()->get('integration.repository');
    }

    public function testCanDelete(): void
    {
        $timeExpired = (new \DateTimeImmutable())->modify('-1 day')->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->handleTask($timeExpired, 0);
    }

    public function testCannotDelete(): void
    {
        $timeExpired = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->handleTask($timeExpired, 1);
    }

    private function handleTask(string $timeExpired, int $numberEntities): void
    {
        $this->connection->executeStatement('DELETE FROM scheduled_task');
        $this->connection->executeStatement('DELETE FROM acl_role');
        $this->connection->executeStatement('DELETE FROM integration');

        $taskId = Uuid::randomHex();
        $originalNextExecution = (new \DateTime())->modify('-10 seconds');
        $interval = 300;

        $this->scheduledTaskRepo->create([
            [
                'id' => $taskId,
                'name' => 'test',
                'scheduledTaskClass' => DeleteCascadeAppsTask::class,
                'runInterval' => $interval,
                'defaultRunInterval' => $interval,
                'status' => ScheduledTaskDefinition::STATUS_QUEUED,
                'nextExecutionTime' => $originalNextExecution,
            ],
        ], Context::createDefaultContext());

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

        $task = new DeleteCascadeAppsTask();
        $task->setTaskId($taskId);

        $handler = new DeleteCascadeAppsHandler(
            $this->scheduledTaskRepo,
            $this->createMock(LoggerInterface::class),
            $this->aclRoleRepo,
            $this->integrationRepo
        );

        $handler($task);

        $aclRoles = $this->aclRoleRepo->search(new Criteria(), Context::createDefaultContext())->getEntities();
        static::assertCount($numberEntities, $aclRoles);

        $integrations = $this->integrationRepo->search(new Criteria(), Context::createDefaultContext())->getEntities();
        static::assertCount($numberEntities, $integrations);
    }
}
