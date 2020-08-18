<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Log;

use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Log\LoggerInterface;

class LoggerFactory
{
    private $rotatingFilePathPattern = '';

    private $defaultFileRotationCount;

    public function __construct(string $rotatingFilePathPattern, int $defaultFileRotationCount = 14)
    {
        $this->rotatingFilePathPattern = $rotatingFilePathPattern;
        $this->defaultFileRotationCount = $defaultFileRotationCount;
    }

    public function createRotating(string $filePrefix, ?int $fileRotationCount = null): LoggerInterface
    {
        $filepath = sprintf($this->rotatingFilePathPattern, $filePrefix);

        $result = new Logger($filePrefix);
        $result->pushHandler(new RotatingFileHandler($filepath, $fileRotationCount ?? $this->defaultFileRotationCount));
        $result->pushProcessor(new PsrLogMessageProcessor());

        return $result;
    }
}
