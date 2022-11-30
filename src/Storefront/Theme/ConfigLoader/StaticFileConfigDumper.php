<?php
declare(strict_types=1);

namespace Shopware\Storefront\Theme\ConfigLoader;

use League\Flysystem\FilesystemOperator;
use Shopware\Core\Framework\Context;
use Shopware\Storefront\Theme\Event\ThemeAssignedEvent;
use Shopware\Storefront\Theme\Event\ThemeConfigChangedEvent;
use Shopware\Storefront\Theme\Event\ThemeConfigResetEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use function json_encode;
use function sprintf;
use const JSON_THROW_ON_ERROR;

/**
 * @internal
 */
class StaticFileConfigDumper implements EventSubscriberInterface
{
    private AbstractConfigLoader $configLoader;

    private FilesystemOperator $filesystem;

    private AbstractAvailableThemeProvider $availableThemeProvider;

    /**
     * @internal
     */
    public function __construct(
        AbstractConfigLoader $configLoader,
        AbstractAvailableThemeProvider $availableThemeProvider,
        FilesystemOperator $filesystem
    ) {
        $this->configLoader = $configLoader;
        $this->filesystem = $filesystem;
        $this->availableThemeProvider = $availableThemeProvider;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ThemeConfigChangedEvent::class => 'dumpConfigFromEvent',
            ThemeAssignedEvent::class => 'dumpConfigFromEvent',
            ThemeConfigResetEvent::class => 'dumpConfigFromEvent',
        ];
    }

    public function dumpConfig(Context $context): void
    {
        $salesChannelToTheme = $this->availableThemeProvider->load($context);
        $this->filesystem->write(StaticFileAvailableThemeProvider::THEME_INDEX, json_encode($salesChannelToTheme, JSON_THROW_ON_ERROR));

        foreach ($salesChannelToTheme as $themeId) {
            $struct = $this->configLoader->load($themeId, $context);

            $path = sprintf('theme-config/%s.json', $themeId);

            $this->filesystem->write($path, json_encode($struct->jsonSerialize(), JSON_THROW_ON_ERROR));
        }
    }

    public function dumpConfigFromEvent(): void
    {
        $this->dumpConfig(Context::createDefaultContext());
    }
}
