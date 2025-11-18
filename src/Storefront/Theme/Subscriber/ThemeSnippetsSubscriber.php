<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Subscriber;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Event\SnippetsThemeResolveEvent;
use Shopware\Storefront\Theme\DatabaseSalesChannelThemeLoader;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Shopware\Storefront\Theme\ThemeRuntimeConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('discovery')]
class ThemeSnippetsSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ThemeRuntimeConfigService $themeRuntimeConfigService,
        private readonly DatabaseSalesChannelThemeLoader $salesChannelThemeLoader
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SnippetsThemeResolveEvent::class => 'onSnippetsThemeResolve',
        ];
    }

    public function onSnippetsThemeResolve(SnippetsThemeResolveEvent $event): void
    {
        $salesChannelId = $event->getSalesChannelId();

        $usedThemes = $this->getUsedThemes($salesChannelId);
        $unusedThemes = $this->getUnusedThemes($usedThemes);

        $event->setUsedThemes($usedThemes);
        $event->setUnusedThemes($unusedThemes);
    }

    /**
     * @return list<string>
     */
    private function getUsedThemes(?string $salesChannelId = null): array
    {
        $usedThemes = [];

        // Load used themes
        if ($salesChannelId !== null) {
            $usedThemes = $this->salesChannelThemeLoader->load($salesChannelId);
        }

        return array_values(array_unique([
            ...$usedThemes,
            StorefrontPluginRegistry::BASE_THEME_NAME, // Storefront snippets should always be loaded
        ]));
    }

    /**
     * @param list<string> $usingThemes
     *
     * @return list<string>
     */
    private function getUnusedThemes(array $usingThemes = []): array
    {
        $availableThemes = $this->themeRuntimeConfigService->getActiveThemeNames();
        $unusedThemes = array_diff($availableThemes, $usingThemes);

        return array_values($unusedThemes);
    }
}
