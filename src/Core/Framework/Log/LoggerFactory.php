<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Log;

use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Log\LoggerInterface;

/**
 * @deprecated tag:v6.6.0 - reason:becomes-internal
 */
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
     * @param value-of<Level::VALUES>|Level $loggerLevel
     */
    public function createRotating(string $filePrefix, ?int $fileRotationCount = null, int|Level $loggerLevel = Level::Debug): LoggerInterface
    {
        $filepath = sprintf($this->rotatingFilePathPattern, $filePrefix);

        $result = new Logger($filePrefix);
        $result->pushHandler(new RotatingFileHandler($filepath, $fileRotationCount ?? $this->defaultFileRotationCount, $loggerLevel));
        $result->pushProcessor(new PsrLogMessageProcessor());

        return $result;
    }
}
