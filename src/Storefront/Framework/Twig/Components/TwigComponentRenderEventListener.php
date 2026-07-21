<?php

declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig\Components;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\UX\TwigComponent\ComponentAttributes;
use Symfony\UX\TwigComponent\Event\PreRenderEvent;

/**
 * @internal
 */
#[Package('framework')]
#[AsEventListener]
class TwigComponentRenderEventListener
{
    public function __construct(
        private readonly string $environment
    ) {
    }

    public function __invoke(PreRenderEvent $event): void
    {
        $mountedComponent = $event->getMountedComponent();
        $variables = $event->getVariables();
        $metadata = $event->getMetadata();
        $attributesVar = $metadata->getAttributesVar();

        // Get the current attributes
        $attributes = $variables[$attributesVar] ?? null;

        if (!$attributes instanceof ComponentAttributes) {
            return;
        }

        $additionalAttributes = [
            'data-component-name' => $metadata->getName(),
        ];

        // If env = DEV add addtional attributes to the component
        if ($this->environment === 'dev') {
            $additionalAttributes['data-component-template'] = $metadata->getTemplate();

            if ($mountedComponent->hasExtraMetadata('hostTemplate')) {
                $hostTemplate = $mountedComponent->getExtraMetadata('hostTemplate');
                $additionalAttributes['data-component-parent'] = $this->pathToComponentName($hostTemplate);
                $additionalAttributes['data-component-parent-template'] = $hostTemplate;
            }
        }

        // Add additional attributes using defaults()
        $newAttributes = $attributes->defaults($additionalAttributes);

        // Update the variables with the new attributes
        $variables[$attributesVar] = $newAttributes;
        $event->setVariables($variables);
    }

    /**
     * Converts a component path to a component name.
     *
     * Example: "components/Sw/Filter/Panel.html.twig" -> "Sw:Filter:Panel"
     *
     * @param string $path The component template path
     *
     * @return string The component name in format "Namespace:Component:Name"
     */
    private function pathToComponentName(string $path): string
    {
        $path = str_starts_with($path, 'components/') ? substr($path, \strlen('components/')) : $path;
        $path = str_ends_with($path, '.html.twig') ? substr($path, 0, -\strlen('.html.twig')) : $path;

        return str_replace('/', ':', $path);
    }
}
