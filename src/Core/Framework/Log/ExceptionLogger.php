<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Log;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

#[Package('checkout')]
class ExceptionLogger
{
    /**
     * @internal
     */
    public function __construct(
        private readonly string $environment,
        private readonly bool $enforceThrow,
        private readonly LoggerInterface $logger
    ) {
    }

    public function logOrThrowException(\Throwable $e, string $level = LogLevel::ERROR): void
    {
        $this->logger->log($level, $e->getMessage());

        if ($this->enforceThrow || $this->environment !== 'prod') {
            throw $e;
        }
    }
}
