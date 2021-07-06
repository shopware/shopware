<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Log;

use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Log\LoggerInterface;

class LoggerFactory
{
    private string $rotatingFilePathPattern = '';

    private int $defaultFileRotationCount;

    public function __construct(string $rotatingFilePathPattern, int $defaultFileRotationCount = 14)
    {
        $this->rotatingFilePathPattern = $rotatingFilePathPattern;
        $this->defaultFileRotationCount = $defaultFileRotationCount;
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
