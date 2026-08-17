<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Context;

use Psr\Log\LoggerInterface;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;

/**
 * @internal
 */
#[Package('framework')]
class AtsContextCacheTrace
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    /**
     * @param list<string> $taxWriteOperations
     */
    public function contextInvalidation(array $taxWriteOperations): void
    {
        $this->trace('context-invalidation', [
            'taxWriteOperations' => $taxWriteOperations,
        ]);
    }

    public function cacheBuildStarted(string $factory, string $cacheKey): void
    {
        $this->trace('context-cache-build-started', [
            'factory' => $factory,
            'cacheKey' => $this->hashCacheKey($cacheKey),
        ]);
    }

    public function cacheBuildCompleted(string $factory, string $cacheKey, int $taxRuleCount): void
    {
        $this->trace('context-cache-build-completed', [
            'factory' => $factory,
            'cacheKey' => $this->hashCacheKey($cacheKey),
            'taxRuleCount' => $taxRuleCount,
        ]);
    }

    public function cacheAccess(string $factory, string $cacheKey, bool $cacheMiss, int $taxRuleCount): void
    {
        $this->trace('context-cache-access', [
            'factory' => $factory,
            'cacheKey' => $this->hashCacheKey($cacheKey),
            'cacheMiss' => $cacheMiss,
            'taxRuleCount' => $taxRuleCount,
        ]);
    }

    /**
     * @param array<string, bool|int|string|list<string>> $context
     */
    private function trace(string $event, array $context): void
    {
        if (EnvironmentHelper::getVariable('ATS_CACHE_TRACE') !== '1') {
            return;
        }

        // Keep the CI artifact free of test data, tokens, and identifiers.
        $this->logger->error('ATS sales channel context cache trace.', ['event' => $event, ...$context]);
    }

    private function hashCacheKey(string $cacheKey): string
    {
        return Hasher::hash($cacheKey, 'sha256');
    }
}
