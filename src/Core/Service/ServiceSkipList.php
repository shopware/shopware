<?php declare(strict_types=1);

namespace Shopware\Core\Service;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Clock\ClockInterface;

/**
 * Remembers services that discovery should not re-fetch from the registry (e.g. gated services while
 * services are disabled), keyed by name with the time they were skipped.
 *
 * Entries expire after {@see BACKSTOP_SECONDS}; an expired entry is re-evaluated on the next run (and
 * re-skipped if still not installable). This bounds the registry traffic while still picking up a
 * service that has become installable - e.g. a gated service whose requirements changed - within the
 * backstop window, without needing any registry-side change.
 *
 * @internal
 */
#[Package('framework')]
class ServiceSkipList
{
    public const BACKSTOP_SECONDS = 604800; // 1 week

    private const CONFIG_KEY = 'core.services.skipped';

    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly ClockInterface $clock,
    ) {
    }

    public function skip(string $name): void
    {
        $skipped = $this->skipped();
        $skipped[$name] = $this->clock->now()->getTimestamp();

        $this->systemConfigService->set(self::CONFIG_KEY, $skipped, null, true);
    }

    public function shouldSkip(string $name): bool
    {
        $skipped = $this->skipped();
        if (!isset($skipped[$name])) {
            return false;
        }

        return ($this->clock->now()->getTimestamp() - (int) $skipped[$name]) < self::BACKSTOP_SECONDS;
    }

    public function clear(): void
    {
        $this->systemConfigService->delete(self::CONFIG_KEY, null, true);
    }

    /**
     * @return array<string, int>
     */
    private function skipped(): array
    {
        $value = $this->systemConfigService->get(self::CONFIG_KEY);

        return \is_array($value) ? $value : [];
    }
}
