<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Subscriber;

use Doctrine\DBAL\Exception as DBALException;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\IOStreamHelper;
use Shopware\Core\System\SystemConfig\DTO\SystemConfigElement;
use Shopware\Core\System\SystemConfig\Service\SystemConfigDefinitionService;
use Shopware\Storefront\Theme\Event\ThemeCompilerEnrichScssVariablesEvent;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('discovery')]
class ThemeCompilerEnrichScssVarSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SystemConfigDefinitionService $systemConfigDefinitionService,
        private readonly StorefrontPluginRegistry $storefrontPluginRegistry
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
                    $this->systemConfigDefinitionService->getResolvedConfiguration(
                        $configuration->getTechnicalName() . '.config',
                        $event->getContext(),
                        $event->getSalesChannelId()
                    )
                );
            }
        } catch (DBALException $e) {
            if (!EnvironmentHelper::getVariable('TESTS_RUNNING')) {
                IOStreamHelper::writeError('Warning: Failed to load plugin css configuration. Ignoring plugin css customizations.', $e);
            }
        }

        foreach ($allConfigs as $tab) {
            foreach ($tab->cards as $card) {
                foreach ($card->elements as $element) {
                    if (!$this->hasCssValue($element)) {
                        continue;
                    }

                    $event->addVariable($element->config['css'], $element->value ?? $element->config['defaultValue']);
                }
            }
        }
    }

    private function hasCssValue(SystemConfigElement $element): bool
    {
        if (!isset($element->config['css'])) {
            return false;
        }

        if (!\is_string($element->value ?? $element->config['defaultValue'])) {
            return false;
        }

        return true;
    }
}
