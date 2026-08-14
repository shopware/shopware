<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Context;

use Psr\Log\LoggerInterface;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class AtsContextCacheTrace
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function contextInvalidation(bool $containsTaxWrite): void
    {
        $this->trace('context-invalidation', [
            'containsTaxWrite' => $containsTaxWrite,
        ]);
    }

    public function cacheAccess(string $factory, bool $cacheMiss, int $taxRuleCount): void
    {
        $this->trace('context-cache-access', [
            'factory' => $factory,
            'cacheMiss' => $cacheMiss,
            'taxRuleCount' => $taxRuleCount,
        ]);
    }

    /**
     * @param array<string, bool|int|string> $context
     */
    private function trace(string $event, array $context): void
    {
        if (EnvironmentHelper::getVariable('ATS_CACHE_TRACE') !== '1') {
            return;
        }

        // Keep the CI artifact free of test data, tokens, and identifiers.
        $this->logger->error('ATS sales channel context cache trace.', ['event' => $event, ...$context]);
    }
}
