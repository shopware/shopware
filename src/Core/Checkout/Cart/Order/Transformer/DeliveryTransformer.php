<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Order\Transformer;

use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\Order\IdStruct;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('checkout')]
class DeliveryTransformer
{
    public static function transformCollection(
        DeliveryCollection $deliveries,
        array $lineItems,
        string $stateId,
        Context $context,
        array $addresses = []
    ): array {
        $output = [];
        foreach ($deliveries as $delivery) {
            $output[] = self::transform($delivery, $lineItems, $stateId, $context, $addresses);
        }

        return $output;
    }

    public static function transform(
        Delivery $delivery,
        array $lineItems,
        string $stateId,
        Context $context,
        array $addresses = []
    ): array {
        $addressId = $delivery->getLocation()->getAddress() ? $delivery->getLocation()->getAddress()->getId() : null;
        $shippingAddress = null;

        if ($addressId !== null && \array_key_exists($addressId, $addresses)) {
            $shippingAddress = $addresses[$addressId];
        } elseif ($delivery->getLocation()->getAddress() !== null) {
            $shippingAddress = AddressTransformer::transform($delivery->getLocation()->getAddress());
        }

        $deliveryData = [
            'id' => self::getId($delivery),
            'shippingDateEarliest' => $delivery->getDeliveryDate()->getEarliest()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'shippingDateLatest' => $delivery->getDeliveryDate()->getLatest()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'shippingMethodId' => $delivery->getShippingMethod()->getId(),
            'shippingOrderAddress' => $shippingAddress,
            'shippingCosts' => $delivery->getShippingCosts(),
            'positions' => [],
            'stateId' => $stateId,
        ];

        $deliveryData = array_filter($deliveryData, fn ($item) => $item !== null);

        foreach ($delivery->getPositions() as $position) {
            $deliveryData['positions'][] = [
                'id' => self::getId($position),
                'price' => $position->getPrice(),
                'orderLineItemId' => $lineItems[$position->getIdentifier()]['id'],
                'orderLineItemVersionId' => $context->getVersionId(),
            ];
        }

        return $deliveryData;
    }

    private static function getId(Struct $struct): ?string
    {
        /** @var IdStruct|null $idStruct */
        $idStruct = $struct->getExtensionOfType(OrderConverter::ORIGINAL_ID, IdStruct::class);
        if ($idStruct !== null) {
            return $idStruct->getId();
        }

        return null;
    }
}
