<?php

declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Log;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\LogEntryCollection;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
class LogEntryTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testLogEntry(): void
    {
        $logEntry = [
            'message' => 'Test message',
            'level' => 100,
            'channel' => 'test',
            'context' => ['test' => 'test'],
            'extra' => ['extra' => 'extra'],
        ];

        /** @var EntityRepository<LogEntryCollection> $logEntryRepository */
        $logEntryRepository = $this->getContainer()->get('log_entry.repository');
        $logEntryRepository->create([$logEntry], Context::createDefaultContext());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('message', 'Test message'));

        $result = $logEntryRepository->search($criteria, Context::createDefaultContext());

        $logEntries = $result->getEntities();
        static::assertCount(1, $logEntries);

        $firstLogEntry = $logEntries->first();
        static::assertNotNull($firstLogEntry);
        static::assertSame('Test message', $firstLogEntry->getMessage());
        static::assertSame(100, $firstLogEntry->getLevel());
        static::assertSame('test', $firstLogEntry->getChannel());
        static::assertSame(['test' => 'test'], $firstLogEntry->getContext());
        static::assertSame(['extra' => 'extra'], $firstLogEntry->getExtra());
    }
}
