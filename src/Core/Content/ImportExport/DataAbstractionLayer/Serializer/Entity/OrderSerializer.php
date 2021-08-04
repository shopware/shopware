<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Struct\Struct;

class OrderSerializer extends EntitySerializer
{
    public function supports(string $entity): bool
    {
        return $entity === OrderDefinition::ENTITY_NAME;
    }

    public function serialize(Config $config, EntityDefinition $definition, $entity): iterable
    {
        if ($entity === null) {
            return;
        }

        if ($entity instanceof Struct) {
            $entity = $entity->jsonSerialize();
        }

        yield from parent::serialize($config, $definition, $entity);

        if (isset($entity['lineItems'])) {
            /** @var OrderLineItemCollection $lineItems */
            $lineItems = $entity['lineItems']->getElements();
            $modifiedLineItems = [];

            foreach ($lineItems as $lineItem) {
                $lineItem = $lineItem->jsonSerialize();

                $modifiedLineItems[] = $lineItem['quantity'] . 'x ' . $lineItem['productId'];
            }

            $entity['lineItems'] = implode('|', $modifiedLineItems);
        }

        if (isset($entity['deliveries'])) {
            $entity['deliveries'] = $entity['deliveries']->first()->jsonSerialize();
            $entity['deliveries']['shippingOrderAddress'] = $entity['deliveries']['shippingOrderAddress']->jsonSerialize();
        }

        yield from $entity;
    }
}
