<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\EventSubscriber\PreHydration;

use Shopware\Core\Content\ContentSystem\Event\PreContentHydrationEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Resolves {{variable}} placeholders in element properties.
 *
 * Single-pass only, no recursive resolution. Placeholders are replaced
 * with values from RenderingSpecification before hydration starts.
 *
 * @internal
 */
#[Package('discovery')]
class PlaceholderResolutionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            PreContentHydrationEvent::class => ['onPreContentHydration', 3000],
        ];
    }

    public function onPreContentHydration(PreContentHydrationEvent $event): void
    {
        // ContentElement is mutable, so changes happen in place
        foreach ($event->elements as $element) {
            $element->replacePlaceholders($event->specification);
        }
    }
}
