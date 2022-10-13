<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Log;

use Monolog\Handler\NullHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Feature;

class LoggerFactory
{
    private string $rotatingFilePathPattern = '';

    private LoggerInterface $logger;

    private int $defaultFileRotationCount;

    /**
     * @internal
     */
    public function __construct(string $rotatingFilePathPattern, LoggerInterface $logger, int $defaultFileRotationCount = 14)
    {
        $this->rotatingFilePathPattern = $rotatingFilePathPattern;
        $this->logger = $logger;
        $this->defaultFileRotationCount = $defaultFileRotationCount;
    }

    /**
     * @param 100|200|250|300|400|500|550|600 $loggerLevel
     */
    public function create(string $filePrefix, ?int $fileRotationCount = null, int $loggerLevel = Logger::DEBUG): LoggerInterface
    {
        $result = new Logger($filePrefix);
        $result->pushProcessor(new PsrLogMessageProcessor());

        /**
         * Use RotatingFileHandler as fallback if Nullhandler or none is given
         * If RotatingFileHandler is given (default configuration) -> use "default" logic for splitted logs
         */
        if (!method_exists($this->logger, 'getHandlers')
            || (
                \count($this->logger->getHandlers() ?? 0) === 1
                && (
                    current($this->logger->getHandlers()) instanceof NullHandler
                    || current($this->logger->getHandlers()) instanceof RotatingFileHandler
                )
            )
        ) {
            $filepath = sprintf($this->rotatingFilePathPattern, $filePrefix);

            $result->pushHandler(new RotatingFileHandler($filepath, $fileRotationCount ?? $this->defaultFileRotationCount, $loggerLevel));

            return $result;
        }

        $result->setHandlers($this->logger->getHandlers());

        return $result;
    }

    /**
     * @deprecated tag:v6.5.0 - Will be removed, use `create` instead
     *
     * @param 100|200|250|300|400|500|550|600 $loggerLevel
     */
    public function createRotating(string $filePrefix, ?int $fileRotationCount = null, int $loggerLevel = Logger::DEBUG): LoggerInterface
    {
        Feature::triggerDeprecationOrThrow('v6.5.0.0', 'Use the create method instead');

        return $this->create($filePrefix, $fileRotationCount, $loggerLevel);
    }
}
