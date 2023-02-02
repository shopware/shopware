<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Theme\fixtures;

use Shopware\Core\Framework\Feature;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Event\ThemeCompilerEnrichScssVariablesEvent as ThemeCompilerEnrichScssVariablesEventDep;
use Shopware\Storefront\Theme\Event\ThemeCompilerEnrichScssVariablesEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class MockThemeVariablesSubscriber implements EventSubscriberInterface
{
    /**
     * @var SystemConfigService
     */
    protected $systemConfig;

    public function __construct(SystemConfigService $systemConfig)
    {
        $this->systemConfig = $systemConfig;
    }

    public static function getSubscribedEvents(): array
    {
        if (Feature::isActive('v6.5.0.0')) {
            return [
                ThemeCompilerEnrichScssVariablesEvent::class => 'onAddVariables',
            ];
        }

        return [
            ThemeCompilerEnrichScssVariablesEventDep::class => 'onAddVariablesDep',
        ];
    }

    /**
     * @deprecated tag:v6.5.0 - Method will be removed. Use onAddVariables instead
     */
    public function onAddVariablesDep(ThemeCompilerEnrichScssVariablesEventDep $event): void
    {
        $event->addVariable('mock-variable-black', '#000000');
        $event->addVariable('mock-variable-special', 'Special value with quotes', true);
    }

    public function onAddVariables(ThemeCompilerEnrichScssVariablesEvent $event): void
    {
        $event->addVariable('mock-variable-black', '#000000');
        $event->addVariable('mock-variable-special', 'Special value with quotes', true);
    }
}
