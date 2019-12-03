<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Logging;

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\BusinessEvent;
use Shopware\Core\Framework\Log\LoggingService;
use Shopware\Core\Framework\Test\Event\TestBusinessEvent;
use Shopware\Core\Framework\Test\Logging\Event\LogAwareTestBusinessEvent;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class LoggingServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var Context
     */
    protected $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = Context::createDefaultContext();
    }

    public function testWriteBusinessEvents(): void
    {
        $handler = new TestHandler();
        $logger = new Logger('testlogger', [$handler]);

        $service = new LoggingService('test', $logger);

        $service->logBusinessEvent(
            new BusinessEvent(
                TestBusinessEvent::EVENT_NAME,
                new TestBusinessEvent($this->context)
            )
        );

        $records = $handler->getRecords();

        static::assertCount(1, $records);
        $testRecord = $records[0];

        static::assertEquals(TestBusinessEvent::EVENT_NAME, $testRecord['message']);
        static::assertEquals('test', $testRecord['context']['environment']);
        static::assertEquals(Logger::DEBUG, $testRecord['level']);
        static::assertEmpty($testRecord['context']['additionalData']);
    }

    /**
     * @depends testWriteBusinessEvents
     */
    public function testWriteLogAwareBusinessEvent(): void
    {
        $handler = new TestHandler();
        $logger = new Logger('testlogger', [$handler]);

        $service = new LoggingService(
            'test',
            $logger
        );

        $service->logBusinessEvent(
            new BusinessEvent(
                LogAwareTestBusinessEvent::EVENT_NAME,
                new LogAwareTestBusinessEvent($this->context)
            )
        );

        $records = $handler->getRecords();
        static::assertCount(1, $records);
        $testRecord = $records[0];

        static::assertEquals(Logger::EMERGENCY, $testRecord['level']);
        static::assertNotEmpty($testRecord['context']['additionalData']);
        static::assertArrayHasKey('awesomekey', $testRecord['context']['additionalData']);
        static::assertEquals($testRecord['context']['additionalData']['awesomekey'], 'awesomevalue');
    }
}
