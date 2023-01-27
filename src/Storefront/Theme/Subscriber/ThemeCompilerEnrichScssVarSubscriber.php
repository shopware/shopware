<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Subscriber;

use Doctrine\DBAL\Exception as DBALException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\Service\ConfigurationService;
use Shopware\Storefront\Theme\Event\ThemeCompilerEnrichScssVariablesEvent;
use Shopware\Storefront\Theme\StorefrontPluginRegistryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('storefront')]
class ThemeCompilerEnrichScssVarSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ConfigurationService $configurationService,
        private readonly StorefrontPluginRegistryInterface $storefrontPluginRegistry
    ) {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
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

        if ($this->storefrontPluginRegistry->getConfigurations()->count() === 0) {
            return;
        }

        try {
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
        } catch (DBALException $e) {
            if (\defined('\STDERR')) {
                fwrite(
                    \STDERR,
                    'Warning: Failed to load plugin css configuration. Ignoring plugin css customizations. Message: '
                    . $e->getMessage() . \PHP_EOL
                );
            }
        }

        foreach ($allConfigs as $card) {
            if (!isset($card['elements']) || !\is_array($card['elements'])) {
                continue;
            }

            foreach ($card['elements'] as $element) {
                if (!$this->hasCssValue($element)) {
                    continue;
                }

                $event->addVariable($element['config']['css'], $element['value'] ?? $element['defaultValue']);
            }
        }
    }

    private function hasCssValue(mixed $element): bool
    {
        if (!\is_array($element)) {
            return false;
        }

        if (!\is_array($element['config'])) {
            return false;
        }

        if (!isset($element['config']['css'])) {
            return false;
        }

        if (!\is_string($element['value'] ?? $element['defaultValue'])) {
            return false;
        }

        return true;
    }
}
