<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\System\SystemConfig\SystemConfigService;

#[Package('discovery')]
class SeedingThemePathBuilder extends AbstractThemePathBuilder
{
    /**
     * Each theme stores its seed under its own key ("<prefix><themeId>") so that saving a seed is a
     * single-row write. A shared map would require a non-atomic read-modify-write: two concurrent
     * compilations of the same sales channel could then overwrite each other's entry and leave a
     * theme pointing at a directory that was never compiled.
     */
    private const SYSTEM_CONFIG_KEY_PREFIX = 'storefront.themeSeeds.';

    /**
     * Legacy sales-channel-wide seed (a single string shared by every theme) used before the seed
     * became per-theme. Kept as a fallback so themes compiled under it keep resolving until they are
     * recompiled. The prefix above is intentionally distinct ("themeSeeds" vs "themeSeed") to avoid
     * key nesting collisions in the system config loader.
     */
    private const LEGACY_SYSTEM_CONFIG_KEY = 'storefront.themeSeed';

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
        $this->systemConfigService->set(self::SYSTEM_CONFIG_KEY_PREFIX . $themeId, $seed, $salesChannelId, false);
    }

    public function getDecorated(): AbstractThemePathBuilder
    {
        throw new DecorationPatternException(self::class);
    }

    private function getSeed(string $salesChannelId, string $themeId): string
    {
        $seed = $this->systemConfigService->get(self::SYSTEM_CONFIG_KEY_PREFIX . $themeId, $salesChannelId);

        if (\is_string($seed) && $seed !== '') {
            return $seed;
        }

        // Fallback for themes still on the legacy sales-channel-wide seed.
        $legacy = $this->systemConfigService->get(self::LEGACY_SYSTEM_CONFIG_KEY, $salesChannelId);

        return \is_string($legacy) ? $legacy : '';
    }
}
