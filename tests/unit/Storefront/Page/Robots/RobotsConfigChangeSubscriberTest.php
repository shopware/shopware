<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Page\Robots;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SystemConfig\Event\SystemConfigChangedEvent;
use Shopware\Storefront\Page\Robots\Parser\ParsedRobots;
use Shopware\Storefront\Page\Robots\Parser\ParseIssue;
use Shopware\Storefront\Page\Robots\Parser\ParseIssueSeverity;
use Shopware\Storefront\Page\Robots\Parser\RobotsDirectiveParser;
use Shopware\Storefront\Page\Robots\RobotsConfigChangeSubscriber;

/**
 * @internal
 */
#[CoversClass(RobotsConfigChangeSubscriber::class)]
class RobotsConfigChangeSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        $events = RobotsConfigChangeSubscriber::getSubscribedEvents();

        static::assertArrayHasKey(SystemConfigChangedEvent::class, $events);
        static::assertSame('onSystemConfigChanged', $events[SystemConfigChangedEvent::class]);
    }

    public function testIgnoresNonRobotsConfigChanges(): void
    {
        $parser = $this->createMock(RobotsDirectiveParser::class);
        $parser->expects($this->never())->method('parse');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');
        $logger->expects($this->never())->method('warning');

        $subscriber = new RobotsConfigChangeSubscriber($parser, $logger);

        $event = new SystemConfigChangedEvent('some.other.config', 'value', null);
        $subscriber->onSystemConfigChanged($event);
    }

    public function testIgnoresEmptyValue(): void
    {
        $parser = $this->createMock(RobotsDirectiveParser::class);
        $parser->expects($this->never())->method('parse');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');
        $logger->expects($this->never())->method('warning');

        $subscriber = new RobotsConfigChangeSubscriber($parser, $logger);

        $event = new SystemConfigChangedEvent('core.basicInformation.robotsRules', '', null);
        $subscriber->onSystemConfigChanged($event);
    }

    public function testIgnoresNonStringValue(): void
    {
        $parser = $this->createMock(RobotsDirectiveParser::class);
        $parser->expects($this->never())->method('parse');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');
        $logger->expects($this->never())->method('warning');

        $subscriber = new RobotsConfigChangeSubscriber($parser, $logger);

        $event = new SystemConfigChangedEvent('core.basicInformation.robotsRules', null, null);
        $subscriber->onSystemConfigChanged($event);
    }

    public function testNoLoggingWhenNoIssues(): void
    {
        $parsed = new ParsedRobots([], []);

        $parser = $this->createMock(RobotsDirectiveParser::class);
        $parser->expects($this->once())
            ->method('parse')
            ->with('User-agent: *')
            ->willReturn($parsed);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');
        $logger->expects($this->never())->method('warning');

        $subscriber = new RobotsConfigChangeSubscriber($parser, $logger);

        $event = new SystemConfigChangedEvent('core.basicInformation.robotsRules', 'User-agent: *', null);
        $subscriber->onSystemConfigChanged($event);
    }

    public function testLogsErrorsCorrectly(): void
    {
        $issues = [
            new ParseIssue(1, 'Invalid line', 'Malformed line: missing colon separator', ParseIssueSeverity::ERROR),
            new ParseIssue(3, 'Another bad', 'Another error', ParseIssueSeverity::ERROR),
        ];

        $parsed = new ParsedRobots([], [], $issues);

        $parser = $this->createMock(RobotsDirectiveParser::class);
        $parser->expects($this->once())
            ->method('parse')
            ->willReturn($parsed);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(2))->method('error')
            ->willReturnCallback(function (string $message, array $context): void {
                static::assertStringContainsString('Robots.txt parsing issue', $message);
                static::assertArrayHasKey('scope', $context);
                static::assertArrayHasKey('lineNumber', $context);
                static::assertArrayHasKey('lineContent', $context);
                static::assertArrayHasKey('severity', $context);
            });
        $logger->expects($this->never())->method('warning');

        $subscriber = new RobotsConfigChangeSubscriber($parser, $logger);

        $event = new SystemConfigChangedEvent('core.basicInformation.robotsRules', 'test', null);
        $subscriber->onSystemConfigChanged($event);
    }

    public function testLogsWarningsCorrectly(): void
    {
        $issues = [
            new ParseIssue(2, 'Unknown: directive', 'Unknown directive type: \'Unknown\'', ParseIssueSeverity::WARNING),
        ];

        $parsed = new ParsedRobots([], [], $issues);

        $parser = $this->createMock(RobotsDirectiveParser::class);
        $parser->expects($this->once())
            ->method('parse')
            ->willReturn($parsed);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');
        $logger->expects($this->once())->method('warning')
            ->willReturnCallback(function (string $message, array $context): void {
                static::assertStringContainsString('Robots.txt parsing issue', $message);
                static::assertArrayHasKey('scope', $context);
                static::assertArrayHasKey('lineNumber', $context);
                static::assertArrayHasKey('lineContent', $context);
                static::assertArrayHasKey('severity', $context);
            });

        $subscriber = new RobotsConfigChangeSubscriber($parser, $logger);

        $event = new SystemConfigChangedEvent('core.basicInformation.robotsRules', 'test', null);
        $subscriber->onSystemConfigChanged($event);
    }

    public function testLogsMixedErrorsAndWarnings(): void
    {
        $issues = [
            new ParseIssue(1, 'Invalid line', 'Malformed line', ParseIssueSeverity::ERROR),
            new ParseIssue(2, 'Unknown: directive', 'Unknown directive type', ParseIssueSeverity::WARNING),
            new ParseIssue(3, 'Bad line', 'Another error', ParseIssueSeverity::ERROR),
        ];

        $parsed = new ParsedRobots([], [], $issues);

        $parser = $this->createMock(RobotsDirectiveParser::class);
        $parser->expects($this->once())
            ->method('parse')
            ->willReturn($parsed);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(2))->method('error');
        $logger->expects($this->once())->method('warning');

        $subscriber = new RobotsConfigChangeSubscriber($parser, $logger);

        $event = new SystemConfigChangedEvent('core.basicInformation.robotsRules', 'test', null);
        $subscriber->onSystemConfigChanged($event);
    }

    public function testLogsWithGlobalScopeWhenNoSalesChannelId(): void
    {
        $issues = [
            new ParseIssue(1, 'Invalid line', 'Test error', ParseIssueSeverity::ERROR),
        ];

        $parsed = new ParsedRobots([], [], $issues);

        $parser = $this->createMock(RobotsDirectiveParser::class);
        $parser->expects($this->once())
            ->method('parse')
            ->willReturn($parsed);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')
            ->with(
                static::stringContains('Robots.txt parsing issue'),
                static::callback(static fn (array $context) => $context['scope'] === 'Global')
            );

        $subscriber = new RobotsConfigChangeSubscriber($parser, $logger);

        $event = new SystemConfigChangedEvent('core.basicInformation.robotsRules', 'test', null);
        $subscriber->onSystemConfigChanged($event);
    }

    public function testLogsWithSalesChannelIdWhenProvided(): void
    {
        $issues = [
            new ParseIssue(1, 'Invalid line', 'Test error', ParseIssueSeverity::ERROR),
        ];

        $parsed = new ParsedRobots([], [], $issues);

        $parser = $this->createMock(RobotsDirectiveParser::class);
        $parser->expects($this->once())
            ->method('parse')
            ->willReturn($parsed);

        $salesChannelId = '018c5c6e7c4d70d8a8e6c89a7b4c8d9e';

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')
            ->with(
                static::stringContains('Robots.txt parsing issue'),
                static::callback(static fn (array $context) => $context['scope'] === $salesChannelId)
            );

        $subscriber = new RobotsConfigChangeSubscriber($parser, $logger);

        $event = new SystemConfigChangedEvent('core.basicInformation.robotsRules', 'test', $salesChannelId);
        $subscriber->onSystemConfigChanged($event);
    }
}
