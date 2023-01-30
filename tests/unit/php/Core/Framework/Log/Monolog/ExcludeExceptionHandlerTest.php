<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Log\Monolog;

use Monolog\Handler\FingersCrossedHandler;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Monolog\ExcludeExceptionHandler;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\Log\Monolog\ExcludeExceptionHandler
 */
class ExcludeExceptionHandlerTest extends TestCase
{
    /**
     * @param list<string> $excludeList
     *
     * @dataProvider cases
     */
    public function testHandler(LogRecord $record, array $excludeList, bool $shouldBePassed): void
    {
        $innerHandler = $this->createMock(FingersCrossedHandler::class);
        $innerHandler->expects($shouldBePassed ? static::once() : static::never())->method('handle')->willReturn(true);

        $handler = new ExcludeExceptionHandler(
            $innerHandler,
            $excludeList
        );

        $handler->handle($record);
    }

    /**
     * @return iterable<string, array{0: LogRecord, 1: list<string>, 2: bool}>
     */
    public function cases(): iterable
    {
        // record, exclude list, should be passed
        yield 'empty record' => [
            new LogRecord(new \DateTimeImmutable(), 'foo', Level::Alert, 'some message'),
            [],
            true,
        ];

        yield 'exception without exclude list' => [
            new LogRecord(
                new \DateTimeImmutable(),
                'foo',
                Level::Alert,
                'some message',
                [
                    'exception' => new \RuntimeException(''),
                ]
            ),
            [],
            true,
        ];

        yield 'exception with exclude list that matches' => [
            new LogRecord(
                new \DateTimeImmutable(),
                'foo',
                Level::Alert,
                'some message',
                [
                    'exception' => new \RuntimeException(''),
                ]
            ),
            [
                \RuntimeException::class,
            ],
            false,
        ];

        yield 'exception with exclude list that not matches' => [
            new LogRecord(
                new \DateTimeImmutable(),
                'foo',
                Level::Alert,
                'some message',
                [
                    'exception' => new \InvalidArgumentException(''),
                ]
            ),
            [
                \RuntimeException::class,
            ],
            true,
        ];
    }
}
