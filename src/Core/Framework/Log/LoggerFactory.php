<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Log;

use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Log\LoggerInterface;

class LoggerFactory
{
    private $rotatingFilePathPattern = '';

    public function __construct(string $rotatingFilePathPattern)
    {
        $this->rotatingFilePathPattern = $rotatingFilePathPattern;
    }

    public function createRotating(string $filePrefix, ?int $fileRotationCount = null): LoggerInterface
    {
        $filepath = sprintf($this->rotatingFilePathPattern, $filePrefix);

        $result = new Logger($filePrefix);
        $result->pushHandler((new RotatingFileHandler($filepath, $fileRotationCount ?? 14)));
        $result->pushProcessor((new PsrLogMessageProcessor()));

        return $result;
    }
}
