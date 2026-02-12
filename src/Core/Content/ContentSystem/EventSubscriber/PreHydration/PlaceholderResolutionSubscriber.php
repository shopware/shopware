<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\EventSubscriber\PreHydration;

use Shopware\Core\Content\ContentSystem\Event\PreContentHydrationEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Resolves {{variable}} placeholders in element properties.
 *
 * Single-pass only, no recursive resolution. Placeholders are replaced
 * with values from RenderingSpecification before hydration starts.
 *
 * @internal
 */
#[AsEventListener(event: PreContentHydrationEvent::class, priority: 3000)]
#[Package('discovery')]
class PlaceholderResolutionSubscriber
{
    public function __invoke(PreContentHydrationEvent $event): void
    {
        // ContentElement is mutable, so changes happen in place
        foreach ($event->elements as $element) {
            $element->replacePlaceholders($event->specification);
        }
    }
}
