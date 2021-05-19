<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Subscriber;

use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CheapestPriceContainer;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Content\Property\PropertyGroupCollection;
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
            $price = $product->getCheapestPrice();

            if ($price instanceof CheapestPriceContainer) {
                $resolved = $price->resolve($event->getContext());
                $product->setCheapestPriceContainer($price);
                $product->setCheapestPrice($resolved);
            }

            $product->setVariation(
                $this->buildVariation($product)
            );

            if ($product instanceof SalesChannelProductEntity) {
                $product->setSortedProperties(
                    $this->sortProperties($product)
                );
            }
        }
    }

    private function sortProperties(SalesChannelProductEntity $product): PropertyGroupCollection
    {
        $properties = $product->getProperties();
        if ($properties === null) {
            return new PropertyGroupCollection();
        }

        $sorted = [];
        foreach ($properties as $option) {
            $origin = $option->getGroup();

            if (!$origin || !$origin->getVisibleOnProductDetailPage()) {
                continue;
            }
            $group = clone $origin;

            $groupId = $group->getId();
            if (\array_key_exists($groupId, $sorted)) {
                \assert($sorted[$groupId]->getOptions() !== null);
                $sorted[$groupId]->getOptions()->add($option);

                continue;
            }

            if ($group->getOptions() === null) {
                $group->setOptions(new PropertyGroupOptionCollection());
            }

            \assert($group->getOptions() !== null);
            $group->getOptions()->add($option);

            $sorted[$groupId] = $group;
        }

        $collection = new PropertyGroupCollection($sorted);
        $collection->sortByPositions();
        $collection->sortByConfig();

        return $collection;
    }

    private function buildVariation(ProductEntity $product): array
    {
        if ($product->getOptions() === null) {
            return [];
        }

        $product->getOptions()->sort(function (PropertyGroupOptionEntity $a, PropertyGroupOptionEntity $b) {
            if ($a->getGroup() === null || $b->getGroup() === null) {
                return $a->getGroupId() <=> $b->getGroupId();
            }

            if ($a->getGroup()->getPosition() === $b->getGroup()->getPosition()) {
                return $a->getGroup()->getTranslation('name') <=> $b->getGroup()->getTranslation('name');
            }

            return $a->getGroup()->getPosition() <=> $b->getGroup()->getPosition();
        });

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
}
