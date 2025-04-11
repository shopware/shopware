<?php

declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig\Components;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\UX\TwigComponent\ComponentAttributes;
use Symfony\UX\TwigComponent\Event\PreRenderEvent;

#[Package('framework')]
#[AsEventListener]
class TwigComponentRenderEventListener
{
    /**
     * @internal
     */
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

        if ($attributes instanceof ComponentAttributes) {
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
        $path = preg_replace('#^components/#', '', $path) ?? $path;
        $path = preg_replace('#\.html\.twig$#', '', $path) ?? $path;
        $parts = explode('/', $path);

        return implode(':', $parts);
    }
}
