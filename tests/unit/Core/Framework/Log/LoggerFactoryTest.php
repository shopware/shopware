<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Log;

use Monolog\Handler\NullHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Shopware\Core\Framework\Log\LoggerFactory;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\Log\LoggerFactory
 */
class LoggerFactoryTest extends \PHPUnit\Framework\TestCase
{
    public function testNullLogHandler(): void
    {
        $providedHandler = [new NullHandler()];
        $mainLogger = new Logger('test_logger', $providedHandler);
        $loggerFactory = new LoggerFactory('test_case', $mainLogger);

        /** @var \Monolog\Logger $createdLogger */
        $createdLogger = $loggerFactory->createRotating('test_file_path');
        $usedHandler = $createdLogger->getHandlers();

        static::assertCount(1, $usedHandler);
        static::assertInstanceOf(RotatingFileHandler::class, current($usedHandler), 'Handler differs from excpected');
    }

    public function testRotatingFileLogHandler(): void
    {
        $providedHandler = [new RotatingFileHandler('test')];
        $mainLogger = new Logger('test_logger', $providedHandler);
        $loggerFactory = new LoggerFactory('test_case', $mainLogger);

        /** @var \Monolog\Logger $createdLogger */
        $createdLogger = $loggerFactory->createRotating('test_file_path');
        $usedHandler = $createdLogger->getHandlers();

        static::assertCount(1, $usedHandler);
        static::assertInstanceOf(RotatingFileHandler::class, current($usedHandler), 'Handler differs from excpected');
    }

    public function testMultipleLogHandlers(): void
    {
        $providedHandler = [
            new RotatingFileHandler('test'),
            new NullHandler(),
        ];
        $mainLogger = new Logger('test_logger', $providedHandler);
        $loggerFactory = new LoggerFactory('test_case', $mainLogger);

        /** @var \Monolog\Logger $createdLogger */
        $createdLogger = $loggerFactory->createRotating('test_file_path');
        $usedHandler = $createdLogger->getHandlers();

        static::assertCount(\count($providedHandler), $usedHandler);
        static::assertSame($providedHandler, $usedHandler, 'Handler differs from excpected');
    }
}
