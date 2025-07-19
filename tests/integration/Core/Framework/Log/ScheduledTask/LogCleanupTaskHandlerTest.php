<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Log\ScheduledTask;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\LogEntryCollection;
use Shopware\Core\Framework\Log\ScheduledTask\LogCleanupTask;
use Shopware\Core\Framework\Log\ScheduledTask\LogCleanupTaskHandler;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\Registry\TaskRegistry;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
class LogCleanupTaskHandlerTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    /**
     * @var EntityRepository<ScheduledTaskCollection>
     */
    private EntityRepository $scheduledTaskRepository;

    /**
     * @var EntityRepository<LogEntryCollection>
     */
    private EntityRepository $logEntryRepository;

    private SystemConfigService $systemConfigService;

    private Connection $connection;

    private Context $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = static::getContainer()->get(Connection::class);
        $this->connection->executeStatement('DELETE FROM `log_entry`');

        $this->systemConfigService = static::getContainer()->get(SystemConfigService::class);
        $this->scheduledTaskRepository = static::getContainer()->get('scheduled_task.repository');
        $this->logEntryRepository = static::getContainer()->get('log_entry.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testCleanupWithNoLimits(): void
    {
        $this->runWithOptions(-1, -1, [1, 2, 3]);
    }

    public function testCleanupWithEntryLimit(): void
    {
        $this->runWithOptions(-1, 2, [1, 2]);
    }

    public function testCleanupWithAgeLimit(): void
    {
        $year = 60 * 60 * 24 * 31 * 12;
        $this->runWithOptions((int) ($year * 1.5), -1, [1]);
    }

    public function testCleanupWithBothLimits(): void
    {
        $year = 60 * 60 * 24 * 31 * 12;
        $this->runWithOptions((int) ($year * 1.5), 2, [1]);
    }

    public function testIsRegistered(): void
    {
        $registry = static::getContainer()->get(TaskRegistry::class);
        $registry->registerTasks();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', LogCleanupTask::getTaskName()));
        $task = $this->scheduledTaskRepository->search($criteria, Context::createDefaultContext())->first();

        static::assertNotNull($task);
        static::assertSame(LogCleanupTask::getDefaultInterval(), $task->getRunInterval());
    }

    /**
     * @param list<int> $logEntryNumbers
     */
    private function runWithOptions(int $age, int $maxEntries, array $logEntryNumbers): void
    {
        $this->systemConfigService->set('core.logging.entryLifetimeSeconds', $age);
        $this->systemConfigService->set('core.logging.entryLimit', $maxEntries);
        $this->writeLogs();

        $handler = new LogCleanupTaskHandler(
            $this->scheduledTaskRepository,
            $this->createMock(LoggerInterface::class),
            $this->systemConfigService,
            $this->connection
        );

        $handler->run();

        $results = $this->logEntryRepository->search(new Criteria(), $this->context);
        static::assertSame(\count($logEntryNumbers), $results->getTotal());

        $entries = $results->getEntities();
        $entriesJson = [];
        foreach ($entries as $entry) {
            $entriesJson[] = $entry->jsonSerialize();
        }

        $entryMessages = array_column($entriesJson, 'message');
        $entryContexts = array_column($entriesJson, 'context');
        static::assertContainsOnlyArray($entryContexts);
        $entryExtras = array_column($entriesJson, 'extra');
        static::assertContainsOnlyArray($entryExtras);
        foreach ($logEntryNumbers as $logEntryNumber) {
            static::assertContains('test' . $logEntryNumber, $entryMessages);
            static::assertContains(['contextTest' . $logEntryNumber => 'test' . $logEntryNumber], $entryContexts);
            static::assertContains(['extraTest' . $logEntryNumber => 'test' . $logEntryNumber], $entryExtras);
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
                    'context' => ['contextTest1' => 'test1'],
                    'extra' => ['extraTest1' => 'test1'],
                    'createdAt' => (new \DateTime('- 1 year'))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ],
                [
                    'message' => 'test2',
                    'level' => 42,
                    'channel' => 'test',
                    'context' => ['contextTest2' => 'test2'],
                    'extra' => ['extraTest2' => 'test2'],
                    'createdAt' => (new \DateTime('- 2 years'))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ],
                [
                    'message' => 'test3',
                    'level' => 1337,
                    'channel' => 'test',
                    'context' => ['contextTest3' => 'test3'],
                    'extra' => ['extraTest3' => 'test3'],
                    'createdAt' => (new \DateTime('- 3 years'))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ],
            ],
            $this->context
        );
    }
}
