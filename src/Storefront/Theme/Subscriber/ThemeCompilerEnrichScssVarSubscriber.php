<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Subscriber;

use Shopware\Core\System\SystemConfig\Service\ConfigurationService;
use Shopware\Storefront\Theme\Event\ThemeCompilerEnrichScssVariablesEvent;
use Shopware\Storefront\Theme\StorefrontPluginRegistryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ThemeCompilerEnrichScssVarSubscriber implements EventSubscriberInterface
{
    private ConfigurationService $configurationService;

    private StorefrontPluginRegistryInterface $storefrontPluginRegistry;

    public function __construct(
        ConfigurationService $configurationService,
        StorefrontPluginRegistryInterface $storefrontPluginRegistry
    ) {
        $this->configurationService = $configurationService;
        $this->storefrontPluginRegistry = $storefrontPluginRegistry;
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents()
    {
        return [
            ThemeCompilerEnrichScssVariablesEvent::class => 'enrichExtensionVars',
        ];
    }

    /**
     * @internal
     */
    public function enrichExtensionVars(ThemeCompilerEnrichScssVariablesEvent $event): void
    {
        $allConfigs = [];
        foreach ($this->storefrontPluginRegistry->getConfigurations() as $configuration) {
            $allConfigs = array_merge(
                $allConfigs,
                $this->configurationService->getResolvedConfiguration(
                    $configuration->getTechnicalName() . '.config',
                    $event->getContext(),
                    $event->getSalesChannelId()
                )
            );
        }

        foreach ($allConfigs as $card) {
            if (isset($card['elements']) && \is_array($card['elements'])) {
                foreach ($card['elements'] as $element) {
                    if (isset($element['config']['css'])) {
                        $event->addVariable($element['config']['css'], $element['value'] ?? $element['defaultValue'] ?? '#fff');
                    }
                }
            }
        }
    }
}
