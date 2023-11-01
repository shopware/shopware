<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Log;

use Monolog\Handler\NullHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Feature;

/**
 * @deprecated tag:v6.6.0 - Will be removed, use monolog channels instead.
 */
#[Package('core')]
class LoggerFactory
{
    /**
     * @internal
     */
    public function __construct(
        private readonly string $rotatingFilePathPattern,
        private readonly LoggerInterface $logger,
        private readonly int $defaultFileRotationCount = 14
    ) {
    }

    /**
     * @param value-of<Level::VALUES>|Level $loggerLevel
     */
    public function createRotating(string $filePrefix, ?int $fileRotationCount = null, int|Level $loggerLevel = Level::Debug): LoggerInterface
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'createRotating')
        );

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
}
