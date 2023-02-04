<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Log;

use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Log\LoggerInterface;

#[Package('core')]
class LoggerFactory
{
    /**
     * @internal
     */
    public function __construct(
        private readonly string $rotatingFilePathPattern,
        private readonly int $defaultFileRotationCount = 14
    ) {
    }

    /**
     * @param 100|200|250|300|400|500|550|600 $loggerLevel
     */
    public function createRotating(string $filePrefix, ?int $fileRotationCount = null, int $loggerLevel = Logger::DEBUG): LoggerInterface
    {
        $filepath = sprintf($this->rotatingFilePathPattern, $filePrefix);

        $result = new Logger($filePrefix);
        $result->pushHandler(new RotatingFileHandler($filepath, $fileRotationCount ?? $this->defaultFileRotationCount, $loggerLevel));
        $result->pushProcessor(new PsrLogMessageProcessor());

        return $result;
    }
}
