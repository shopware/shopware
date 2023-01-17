<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Log\Monolog;

use Monolog\Handler\FingersCrossedHandler;
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
     * @param array{message: string, context: array<mixed>, level: 100|200|250|300|400|500|550|600, level_name: 'ALERT'|'CRITICAL'|'DEBUG'|'EMERGENCY'|'ERROR'|'INFO'|'NOTICE'|'WARNING', channel: string, datetime: \DateTimeImmutable, extra: array<mixed>} $record
     * @param array<int, string> $excludeList
     *
     * @dataProvider cases
     */
    public function testHandler(array $record, array $excludeList, bool $shouldBePassed): void
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
     * @return iterable<string, array<int, bool|array<mixed>>>
     */
    public function cases(): iterable
    {
        // record, exclude list, should be passed
        yield 'empty record' => [
            [],
            [],
            true,
        ];

        yield 'exception without exclude list' => [
            [
                'context' => [
                    'exception' => new \RuntimeException(''),
                ],
            ],
            [],
            true,
        ];

        yield 'exception with exclude list that matches' => [
            [
                'context' => [
                    'exception' => new \RuntimeException(''),
                ],
            ],
            [
                \RuntimeException::class,
            ],
            false,
        ];

        yield 'exception with exclude list that not matches' => [
            [
                'context' => [
                    'exception' => new \InvalidArgumentException(''),
                ],
            ],
            [
                \RuntimeException::class,
            ],
            true,
        ];
    }
}
