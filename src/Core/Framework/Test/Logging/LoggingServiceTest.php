<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Logging;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\Service\Event\MailErrorEvent;
use Shopware\Core\Content\Test\Flow\TestFlowBusinessEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\BusinessEvent;
use Shopware\Core\Framework\Event\FlowLogEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\LoggingService;
use Shopware\Core\Framework\Test\Event\TestBusinessEvent;
use Shopware\Core\Framework\Test\Logging\Event\LogAwareTestBusinessEvent;
use Shopware\Core\Framework\Test\Logging\Event\LogAwareTestFlowEvent;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
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

    /**
     * @throws Exception
     */
    protected function tearDown(): void
    {
        if (!Feature::isActive('v6.5.0.0')) {
            return;
        }
        parent::tearDown();
        $connection = $this->getContainer()->get(Connection::class);
        $connection->executeStatement('DELETE FROM `log_entry`');
    }

    public function testWriteBusinessEvents(): void
    {
        Feature::skipTestIfActive('v6.5.0.0', $this);

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
        Feature::skipTestIfActive('v6.5.0.0', $this);
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

    public function testWriteFlowEvents(): void
    {
        Feature::skipTestIfInActive('v6.5.0.0', $this);
        $handler = new TestHandler();
        $logger = new Logger('testlogger', [$handler]);

        $service = new LoggingService('test', $logger);

        $service->logFlowEvent(
            new FlowLogEvent(TestFlowBusinessEvent::EVENT_NAME, new TestFlowBusinessEvent($this->context))
        );

        $records = $handler->getRecords();

        static::assertCount(1, $records);
        $testRecord = $records[0];

        static::assertEquals(TestFlowBusinessEvent::EVENT_NAME, $testRecord['message']);
        static::assertEquals('test', $testRecord['context']['environment']);
        static::assertEquals(Logger::DEBUG, $testRecord['level']);
        static::assertEmpty($testRecord['context']['additionalData']);
    }

    public function testWriteMailSendLogEvents(): void
    {
        Feature::skipTestIfInActive('v6.5.0.0', $this);
        $handler = new TestHandler();
        $logger = new Logger('testlogger', [$handler]);

        $service = new LoggingService('test', $logger);

        $service->logFlowEvent(
            new FlowLogEvent(TestFlowBusinessEvent::EVENT_NAME, new MailErrorEvent($this->context, 400, new Exception()))
        );

        $records = $handler->getRecords();

        static::assertCount(1, $records);
        $testRecord = $records[0];

        static::assertEquals(MailErrorEvent::NAME, $testRecord['message']);
        static::assertEquals('test', $testRecord['context']['environment']);
        static::assertEquals(Logger::ERROR, $testRecord['level']);
    }

    /**
     * @depends testWriteFlowEvents
     */
    public function testWriteLogAwareFlowEvent(): void
    {
        Feature::skipTestIfInActive('v6.5.0.0', $this);
        $handler = new TestHandler();
        $logger = new Logger('testlogger', [$handler]);

        $service = new LoggingService(
            'test',
            $logger
        );

        $service->logFlowEvent(
            new FlowLogEvent(LogAwareTestFlowEvent::EVENT_NAME, new LogAwareTestFlowEvent($this->context))
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
