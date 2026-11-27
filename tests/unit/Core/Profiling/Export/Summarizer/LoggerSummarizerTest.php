<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Profiling\Export\Summarizer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Profiling\Export\Summarizer\LoggerSummarizer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\DataCollector\LoggerDataCollector;
use Symfony\Component\HttpKernel\DataCollector\TimeDataCollector;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(LoggerSummarizer::class)]
class LoggerSummarizerTest extends TestCase
{
    public function testSupportsOnlyTheLoggerCollector(): void
    {
        static::assertTrue((new LoggerSummarizer())->supports(new LoggerDataCollector()));
        static::assertFalse((new LoggerSummarizer())->supports(new TimeDataCollector()));
    }

    public function testGroupsWarningsErrorsAndDeprecationsWithoutContext(): void
    {
        $logs = [];
        for ($i = 0; $i < 3; ++$i) {
            $logs[] = $this->log(1, 200, 'INFO', 'php', 'User Deprecated: Old option', new \ErrorException('Old option', 0, \E_USER_DEPRECATED, 'a.php', 1));
        }
        $logs[] = $this->log(2, 200, 'INFO', 'php', 'User Deprecated: Old method', new \ErrorException('Old method', 0, \E_USER_DEPRECATED, 'b.php', 2));
        $logs[] = $this->log(3, 200, 'INFO', 'request', 'Matched route');
        $logs[] = $this->log(4, 300, 'WARNING', 'app', 'Slow request', null, ['token' => 'secret']);
        $logs[] = $this->log(5, 300, 'WARNING', 'app', 'Slow request');
        $logs[] = $this->log(6, 400, 'ERROR', 'app', 'Connection refused');

        $collector = new LoggerDataCollector(new DebugLoggerStub($logs));
        $collector->lateCollect();

        static::assertSame(
            [
                'errors' => 1,
                'warnings' => 2,
                'deprecations' => 4,
                'silenced' => 0,
                'entries' => [
                    ['level' => 'WARNING', 'channel' => 'app', 'message' => 'Slow request', 'count' => 2],
                    ['level' => 'ERROR', 'channel' => 'app', 'message' => 'Connection refused', 'count' => 1],
                ],
                'topDeprecations' => [
                    ['message' => 'User Deprecated: Old option', 'count' => 3],
                    ['message' => 'User Deprecated: Old method', 'count' => 1],
                ],
            ],
            (new LoggerSummarizer())->summarize($collector, null),
        );
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array{channel: string, context: array<string, mixed>, message: string, priority: int, priorityName: string, timestamp: int, timestamp_rfc3339: string}
     */
    private function log(int $timestamp, int $priority, string $priorityName, string $channel, string $message, ?\ErrorException $exception = null, array $context = []): array
    {
        if ($exception !== null) {
            $context['exception'] = $exception;
        }

        return [
            'timestamp' => $timestamp,
            'timestamp_rfc3339' => (new \DateTimeImmutable('@' . $timestamp))->format(\DATE_RFC3339_EXTENDED),
            'message' => $message,
            'priority' => $priority,
            'priorityName' => $priorityName,
            'context' => $context,
            'channel' => $channel,
        ];
    }
}

/**
 * @internal
 */
class DebugLoggerStub implements DebugLoggerInterface
{
    /**
     * @param list<array{channel: string, context: array<string, mixed>, message: string, priority: int, priorityName: string, timestamp: int, timestamp_rfc3339: string}> $logs
     */
    public function __construct(private readonly array $logs)
    {
    }

    public function getLogs(?Request $request = null): array
    {
        return $this->logs;
    }

    public function countErrors(?Request $request = null): int
    {
        return \count(array_filter($this->logs, static fn (array $log): bool => $log['priority'] >= 400));
    }

    public function clear(): void
    {
    }
}
