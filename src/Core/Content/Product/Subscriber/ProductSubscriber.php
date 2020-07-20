<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Subscriber;

use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

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
            $product->setVariation(
                $this->buildVariation($product)
            );
        }
    }

    private function buildVariation(ProductEntity $product): array
    {
        if (!$product->getOptions()) {
            return [];
        }

        $parts = [];

        if (!$product->getConfiguratorGroupConfig()) {
            // fallback - simply take all option names unordered
            $names = $product->getOptions()->map(function (PropertyGroupOptionEntity $option) {
                if (!$option->getGroup()) {
                    return [];
                }

                return [
                    'group' => $option->getGroup()->getTranslation('name'),
                    'option' => $option->getTranslation('name'),
                ];
            });

            return array_values($names);
        }

        // collect option names in order of the configuration
        foreach ($product->getConfiguratorGroupConfig() as $groupConfig) {
            $option = $product->getOptions()
                ->filterByGroupId($groupConfig['id'])
                ->first();

            if (!$option) {
                continue;
            }

            if ($option->getGroup()) {
                $parts[] = [
                    'group' => $option->getGroup()->getTranslation('name'),
                    'option' => $option->getTranslation('name'),
                ];
            }
        }

        return $parts;
    }
}
