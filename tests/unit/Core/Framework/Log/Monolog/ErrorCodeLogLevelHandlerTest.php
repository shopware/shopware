<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Log\Monolog;

use Monolog\Handler\FingersCrossedHandler;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Framework\Log\Monolog\ErrorCodeLogLevelHandler;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

/**
 * @internal
 */
#[CoversClass(ErrorCodeLogLevelHandler::class)]
class ErrorCodeLogLevelHandlerTest extends TestCase
{
    /**
     * @param array<string, value-of<Level::NAMES>|LogLevel::*|'Debug'|'Info'|'Notice'|'Warning'|'Error'|'Critical'|'Alert'|'Emergency'> $errorCodeLogLevelMapping
     */
    #[DataProvider('cases')]
    public function testHandler(LogRecord $record, array $errorCodeLogLevelMapping, Level $expectedLogLevel): void
    {
        $innerHandler = $this->createMock(FingersCrossedHandler::class);
        $innerHandler->expects(static::once())
            ->method('handle')
            ->willReturnCallback(static function (LogRecord $record) use ($expectedLogLevel): bool {
                static::assertEquals($expectedLogLevel, $record->level);

                return true;
            });

        $handler = new ErrorCodeLogLevelHandler(
            $innerHandler,
            $errorCodeLogLevelMapping
        );

        $handler->handle($record);
    }

    /**
     * @return iterable<string, array{0: LogRecord, 1: array<string, value-of<Level::NAMES>|LogLevel::*|'Debug'|'Info'|'Notice'|'Warning'|'Error'|'Critical'|'Alert'|'Emergency'>, 2: Level}>
     */
    public static function cases(): iterable
    {
        $logRecord = new LogRecord(new \DateTimeImmutable(), 'foo', Level::Alert, 'some message');
        yield 'log level stays same without exception' => [
            $logRecord,
            [],
            Level::Alert,
        ];

        $logRecord = new LogRecord(new \DateTimeImmutable(), 'foo', Level::Alert, 'some message', ['exception' => new \RuntimeException('')]);
        yield 'log level stays same without shopware exception' => [
            $logRecord,
            [],
            Level::Alert,
        ];

        $logRecord = new LogRecord(new \DateTimeImmutable(), 'foo', Level::Alert, 'some message', ['exception' => ProductException::categoryNotFound(Uuid::randomHex())]);
        yield 'log level stays same without error code mapping' => [
            $logRecord,
            [],
            Level::Alert,
        ];

        yield 'log level stays same without matching error code mapping' => [
            $logRecord,
            [
                ProductException::PRODUCT_INVALID_CHEAPEST_PRICE_FACADE => 'notice',
            ],
            Level::Alert,
        ];

        yield 'log level is manipulated when error code mapping matches' => [
            $logRecord,
            [
                ProductException::CATEGORY_NOT_FOUND => 'notice',
            ],
            Level::Notice,
        ];

        $logRecord = new LogRecord(
            new \DateTimeImmutable(),
            'foo',
            Level::Alert,
            'some message',
            ['exception' => new HandlerFailedException(
                new Envelope(new \stdClass()),
                [ProductException::categoryNotFound(Uuid::randomHex())]
            )]
        );
        yield 'log level is manipulated based on inner exception for HandlerFailedException' => [
            $logRecord,
            [
                ProductException::CATEGORY_NOT_FOUND => 'notice',
            ],
            Level::Notice,
        ];

        yield 'log level is not manipulated when inner HandlerFailedException does not match' => [
            $logRecord,
            [
                ProductException::PRODUCT_INVALID_CHEAPEST_PRICE_FACADE => 'notice',
            ],
            Level::Alert,
        ];
    }
}
