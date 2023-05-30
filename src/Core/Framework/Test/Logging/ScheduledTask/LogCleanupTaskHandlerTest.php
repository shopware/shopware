<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Logging\ScheduledTask;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\ScheduledTask\LogCleanupTask;
use Shopware\Core\Framework\Log\ScheduledTask\LogCleanupTaskHandler;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\Registry\TaskRegistry;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskEntity;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
class LogCleanupTaskHandlerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private EntityRepository $scheduledTaskRepository;

    private EntityRepository $logEntryRepository;

    private SystemConfigService $systemConfigService;

    private Connection $connection;

    private Context $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->getContainer()->get(Connection::class);
        $this->connection->executeStatement('DELETE FROM `log_entry`');

        $this->systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        $this->scheduledTaskRepository = $this->getContainer()->get('scheduled_task.repository');
        $this->logEntryRepository = $this->getContainer()->get('log_entry.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testCleanupWithNoLimits(): void
    {
        $this->runWithOptions(-1, -1, ['test1', 'test2', 'test3']);
    }

    public function testCleanupWithEntryLimit(): void
    {
        $this->runWithOptions(-1, 1, ['test1']);
    }

    public function testCleanupWithAgeLimit(): void
    {
        $year = 60 * 60 * 24 * 31 * 12;
        $this->runWithOptions((int) ($year * 1.5), -1, ['test1']);
    }

    public function testCleanupWithBothLimits(): void
    {
        $year = 60 * 60 * 24 * 31 * 12;
        $this->runWithOptions((int) ($year * 1.5), 2, ['test1']);
    }

    public function testIsRegistered(): void
    {
        $registry = $this->getContainer()->get(TaskRegistry::class);
        $registry->registerTasks();

        $scheduledTaskRepository = $this->getContainer()->get('scheduled_task.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', LogCleanupTask::getTaskName()));
        /** @var ScheduledTaskEntity|null $task */
        $task = $scheduledTaskRepository->search($criteria, Context::createDefaultContext())->first();

        static::assertNotNull($task);
        static::assertSame(LogCleanupTask::getDefaultInterval(), $task->getRunInterval());
    }

    /**
     * @param list<string> $expectedMessages
     */
    private function runWithOptions(int $age, int $maxEntries, array $expectedMessages): void
    {
        $this->systemConfigService->set('core.logging.entryLifetimeSeconds', $age);
        $this->systemConfigService->set('core.logging.entryLimit', $maxEntries);
        $this->writeLogs();

        $handler = new LogCleanupTaskHandler(
            $this->scheduledTaskRepository,
            $this->systemConfigService,
            $this->connection
        );

        $handler->run();

        $results = $this->logEntryRepository->search(new Criteria(), $this->context);
        static::assertEquals(\count($expectedMessages), $results->getTotal());

        $entries = $results->getEntities();
        $entriesJson = [];
        foreach ($entries as $entry) {
            $entriesJson[] = $entry->jsonSerialize();
        }

        $entryMessages = array_column($entriesJson, 'message');
        foreach ($expectedMessages as $message) {
            static::assertContains($message, $entryMessages);
        }
    }

    private function writeLogs(): void
    {
        $this->logEntryRepository->create(
            [
                [
                    'message' => 'test1',
                    'level' => 12,
                    'channel' => 'test',
                    'createdAt' => (new \DateTime('- 1 year'))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ],
                [
                    'message' => 'test2',
                    'level' => 42,
                    'channel' => 'test',
                    'createdAt' => (new \DateTime('- 2 years'))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ],
                [
                    'message' => 'test3',
                    'level' => 1337,
                    'channel' => 'test',
                    'createdAt' => (new \DateTime('- 3 years'))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ],
            ],
            $this->context
        );
    }
}
