<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\System\SystemConfig\SystemConfigService;

#[Package('discovery')]
class SeedingThemePathBuilder extends AbstractThemePathBuilder
{
    private const SYSTEM_CONFIG_KEY = 'storefront.themeSeed';

    /**
     * Reserved map key holding the legacy sales-channel-wide seed as a fallback for themes that
     * were compiled before the seed became per-theme. It is never a valid theme id (those are hex).
     */
    private const SHARED_SEED_KEY = 'shared';

    /**
     * @internal
     */
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    public function assemblePath(string $salesChannelId, string $themeId): string
    {
        return $this->generateNewPath($salesChannelId, $themeId, $this->getSeed($salesChannelId, $themeId));
    }

    public function generateNewPath(string $salesChannelId, string $themeId, string $seed): string
    {
        return Hasher::hash($themeId . $salesChannelId . $seed);
    }

    public function saveSeed(string $salesChannelId, string $themeId, string $seed): void
    {
        // Store the seed per (sales channel, theme) so compiling one theme does not change the
        // asset path of another theme in the same sales channel.
        $seeds = $this->readSeeds($salesChannelId);
        $seeds[$themeId] = $seed;

        $this->systemConfigService->set(self::SYSTEM_CONFIG_KEY, $seeds, $salesChannelId, false);
    }

    public function getDecorated(): AbstractThemePathBuilder
    {
        throw new DecorationPatternException(self::class);
    }

    private function getSeed(string $salesChannelId, string $themeId): string
    {
        $value = $this->systemConfigService->get(self::SYSTEM_CONFIG_KEY, $salesChannelId);

        // Legacy: a single sales-channel-wide seed shared by every theme.
        if (\is_string($value)) {
            return $value;
        }

        if (\is_array($value)) {
            $seed = $value[$themeId] ?? $value[self::SHARED_SEED_KEY] ?? '';

            return \is_string($seed) ? $seed : '';
        }

        return '';
    }

    /**
     * @return array<string, string>
     */
    private function readSeeds(string $salesChannelId): array
    {
        $value = $this->systemConfigService->get(self::SYSTEM_CONFIG_KEY, $salesChannelId);

        if (\is_array($value)) {
            /** @var array<string, string> $value */
            return $value;
        }

        // Migrate a legacy sales-channel-wide seed into the per-theme map, keeping it as the
        // fallback so themes compiled under it keep resolving until they are recompiled.
        if (\is_string($value) && $value !== '') {
            return [self::SHARED_SEED_KEY => $value];
        }

        return [];
    }
}
