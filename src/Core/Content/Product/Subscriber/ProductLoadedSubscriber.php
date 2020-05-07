<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Subscriber;

use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use function Flag\next7399;

class ProductLoadedSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        // Return the events to listen to as array like this:  <event to listen to> => <method to execute>
        return [
            ProductEvents::PRODUCT_LOADED_EVENT => [
                ['addVariantCharacteristics'],
            ],
        ];
    }

    public function addVariantCharacteristics(EntityLoadedEvent $event): void
    {
        if (!next7399()) {
            return;
        }

        /** @var ProductEntity $product */
        foreach ($event->getEntities() as $product) {
            if (!$product->getOptions()) {
                continue;
            }

            $parts = [];

            if ($product->getConfiguratorGroupConfig()) {
                // collect option names in order of the configuration
                foreach ($product->getConfiguratorGroupConfig() as $groupConfig) {
                    foreach ($product->getOptions() as $option) {
                        if ($groupConfig['id'] === $option->getGroupId()) {
                            $parts[] = $option->getName();
                        }
                    }
                }
            } else {
                // fallback - simply take all option names unordered
                foreach ($product->getOptions() as $option) {
                    $parts[] = $option->getName();
                }
            }

            $product->setVariantCharacteristics(implode(' - ', $parts));
        }
    }
}
