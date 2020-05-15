<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Subscriber;

use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use function Flag\next7399;

class ProductSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        // Return the events to listen to as array like this:  <event to listen to> => <method to execute>
        return [
            ProductEvents::PRODUCT_LOADED_EVENT => [
                ['loaded'],
            ],
        ];
    }

    public function loaded(EntityLoadedEvent $event): void
    {
        /** @var ProductEntity $product */
        foreach ($event->getEntities() as $product) {
            $product->setVariantCharacteristics(
                $this->buildVariantCharacteristics($product)
            );
        }
    }

    private function buildVariantCharacteristics(ProductEntity $product): array
    {
        if (!$product->getOptions() || !next7399()) {
            return [];
        }

        $parts = [];

        if (!$product->getConfiguratorGroupConfig()) {
            // fallback - simply take all option names unordered
            return $product->getOptions()->map(function (PropertyGroupOptionEntity $option) {
                return $option->getTranslation('name');
            });
        }

        // collect option names in order of the configuration
        foreach ($product->getConfiguratorGroupConfig() as $groupConfig) {
            $option = $product->getOptions()
                ->filterByGroupId($groupConfig['id'])
                ->first();

            if (!$option) {
                continue;
            }

            $parts[] = $option->getTranslation('name');
        }

        return $parts;
    }
}
