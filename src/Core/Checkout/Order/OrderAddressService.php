<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order;

use Shopware\Core\Checkout\Cart\Order\Transformer\AddressTransformer;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

#[Package('checkout')]
class OrderAddressService
{
    private const TYPE_BILLING = 'billing';
    private const TYPE_SHIPPING = 'shipping';

    /**
     * @internal
     */
    public function __construct(
        protected EntityRepository $orderRepository,
        protected EntityRepository $orderAddressRepository,
        protected EntityRepository $customerAddressRepository,
        protected EntityRepository $orderDeliveryRepository,
    ) {
    }

    /**
     * @param array<int, array{customerAddressId: string, type: string, deliveryId?: string}> $addressMappings
     */
    public function updateOrderAddresses(
        string $orderId,
        array $addressMappings,
        Context $context
    ): void {
        $this->validateMappings($addressMappings);

        $criteria = (new Criteria([$orderId]))->addAssociation('deliveries');

        /** @var ?OrderEntity $order */
        $order = $this->orderRepository->search($criteria, $context)->get($orderId);

        if (!$order) {
            throw OrderException::orderNotFound($orderId);
        }

        /**
         * We want to reuse the existing address ids because deletion is restricted,
         * and we don't want to clutter the database with unused addresses.
         * So first we try to use the ones we already have, and when we run out we create new ones.
         * This is why we keep track of the ones we already used here.
         */
        $updatedAddressIds = [];
        foreach ($addressMappings as $addressMapping) {
            switch ($addressMapping['type']) {
                case self::TYPE_BILLING:
                    $newOrderAddressId = $this->handleBillingAddress(
                        $order,
                        $addressMapping,
                        $addressMappings,
                        $updatedAddressIds,
                        $context
                    );

                    break;
                case self::TYPE_SHIPPING:
                    /** @var array{customerAddressId: string, type: string, deliveryId: string} $addressMapping */
                    $newOrderAddressId = $this->handleShippingAddress(
                        $order,
                        $addressMapping,
                        $addressMappings,
                        $updatedAddressIds,
                        $context
                    );

                    break;
                default:
                    throw OrderException::invalidOrderAddressMapping('Invalid type');
            }

            $updatedAddressIds[] = $newOrderAddressId;
        }
    }

    /**
     * @param array{customerAddressId: string, type: string} $mapping
     * @param array<int, array{customerAddressId: string, type: string, deliveryId?: string}> $allMappings
     * @param array<int, string> $alreadyUpdatedIds
     */
    private function handleBillingAddress(
        OrderEntity $order,
        array $mapping,
        array $allMappings,
        array $alreadyUpdatedIds,
        Context $context
    ): string {
        $newOrderAddressId = $this->getNewBillingOrderAddressId($order, $allMappings, $alreadyUpdatedIds);

        $this->createOrderAddressFromCustomerAddress(
            $order->getId(),
            $mapping['customerAddressId'],
            $newOrderAddressId,
            $context
        );

        $this->orderRepository->update([
            [
                'id' => $order->getId(),
                'billingAddressId' => $newOrderAddressId,
                'billingAddressVersionId' => $context->getVersionId(),
            ],
        ], $context);

        return $newOrderAddressId;
    }

    /**
     * @param array{customerAddressId: string, type: string, deliveryId: string} $mapping
     * @param array<int, array{customerAddressId: string, type: string, deliveryId?: string}> $allMappings
     * @param array<int, string> $alreadyUpdatedIds
     */
    private function handleShippingAddress(
        OrderEntity $order,
        array $mapping,
        array $allMappings,
        array $alreadyUpdatedIds,
        Context $context
    ): string {
        $deliveryId = $mapping['deliveryId'];

        /** @var OrderDeliveryCollection $deliveries */
        $deliveries = $order->getDeliveries();

        /** @var OrderDeliveryEntity|null $shippingDelivery */
        $shippingDelivery = $deliveries->get($deliveryId);

        if ($shippingDelivery === null) {
            throw OrderException::orderDeliveryNotFound($deliveryId);
        }

        $newOrderAddressId = $this->getShippingOrderAddressId($order, $deliveryId, $allMappings, $alreadyUpdatedIds);

        $this->createOrderAddressFromCustomerAddress(
            $order->getId(),
            $mapping['customerAddressId'],
            $newOrderAddressId,
            $context
        );

        $this->orderDeliveryRepository->update([
            [
                'id' => $deliveryId,
                'shippingOrderAddressId' => $newOrderAddressId,
                'shippingOrderAddressVersionId' => $context->getVersionId(),
            ],
        ], $context);

        return $newOrderAddressId;
    }

    private function createOrderAddressFromCustomerAddress(
        string $orderId,
        string $customerAddressId,
        string $newOrderAddressId,
        Context $context
    ): void {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('customer_address.id', $customerAddressId));

        $customerAddress = $this->customerAddressRepository->search($criteria, $context)->get($customerAddressId);
        if (!$customerAddress instanceof CustomerAddressEntity) {
            throw OrderException::customerAddressNotFound($customerAddressId);
        }

        $newOrderAddress = AddressTransformer::transform($customerAddress);

        $newOrderAddress['id'] = $newOrderAddressId;
        $newOrderAddress['orderId'] = $orderId;

        $this->orderAddressRepository->upsert([$newOrderAddress], $context);
    }

    /**
     * @param array<int, array{customerAddressId: string, type: string, deliveryId?: string}> $allMappings
     * @param array<int, string> $alreadyUpdatedIds
     */
    private function getNewBillingOrderAddressId(
        OrderEntity $order,
        array $allMappings,
        array $alreadyUpdatedIds
    ): string {
        $newOrderAddressId = $order->getBillingAddressId();

        // If it's already used, we need to create a new one
        if (\in_array($newOrderAddressId, $alreadyUpdatedIds, true)) {
            return Uuid::randomHex();
        }

        // If it's used also as a shipping address, but that will be overwritten anyway we can still reuse it
        $deliveryId = $this->getDeliveryIdForOrderAddressId($order, $newOrderAddressId);

        if ($deliveryId !== null && !$this->mappingContainsShippingAddress($deliveryId, $allMappings)) {
            return Uuid::randomHex();
        }

        return $newOrderAddressId;
    }

    /**
     * @param array<int, array{customerAddressId: string, type: string, deliveryId?: string}> $allMappings
     * @param array<int, string> $alreadyUpdatedIds
     */
    private function getShippingOrderAddressId(
        OrderEntity $order,
        string $deliveryId,
        array $allMappings,
        array $alreadyUpdatedIds
    ): string {
        $newOrderAddressId = $this->getOrderAddressIdForDeliveryId($order, $deliveryId);

        // If it's already used, we need to create a new one
        if (\in_array($newOrderAddressId, $alreadyUpdatedIds, true)) {
            return Uuid::randomHex();
        }

        // If it's the same as the billing address, but billing address will be overwritten anyway we can still reuse it
        if ($newOrderAddressId === $order->getBillingAddressId() && !$this->mappingContainsBillingAddress($allMappings)) {
            return Uuid::randomHex();
        }

        return $newOrderAddressId;
    }

    /**
     * @param array<int, array{customerAddressId: string, type: string, deliveryId?: string}> $mappings
     */
    private function mappingContainsBillingAddress(array $mappings): bool
    {
        foreach ($mappings as $mapping) {
            if ($mapping['type'] === self::TYPE_BILLING) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, array{customerAddressId: string, type: string, deliveryId?: string}> $mappings
     */
    private function mappingContainsShippingAddress(string $deliveryId, array $mappings): bool
    {
        foreach ($mappings as $mapping) {
            if ($mapping['type'] === self::TYPE_SHIPPING && isset($mapping['deliveryId']) && $mapping['deliveryId'] === $deliveryId) {
                return true;
            }
        }

        return false;
    }

    private function getDeliveryIdForOrderAddressId(OrderEntity $order, string $orderAddressId): ?string
    {
        /** @var OrderDeliveryCollection $deliveries */
        $deliveries = $order->getDeliveries();

        foreach ($deliveries as $delivery) {
            if ($delivery->getShippingOrderAddressId() === $orderAddressId) {
                return $delivery->getId();
            }
        }

        return null;
    }

    private function getOrderAddressIdForDeliveryId(OrderEntity $order, string $deliveryId): string
    {
        /** @var OrderDeliveryCollection $deliveries */
        $deliveries = $order->getDeliveries();

        /** @var OrderDeliveryEntity|null $delivery */
        $delivery = $deliveries->get($deliveryId);

        if ($delivery === null) {
            throw OrderException::orderDeliveryNotFound($deliveryId);
        }

        return $delivery->getShippingOrderAddressId();
    }

    /**
     * @param array<int, array{customerAddressId: string, type: string, deliveryId?: string}> $mappings
     */
    private function validateMappings(array $mappings): void
    {
        $billingAddressCount = 0;
        foreach ($mappings as $mapping) {
            if (!isset($mapping['customerAddressId'], $mapping['type'])) {
                throw OrderException::invalidOrderAddressMapping('customerAddressId and type are required');
            }

            if (!\in_array($mapping['type'], [self::TYPE_BILLING, self::TYPE_SHIPPING], true)) {
                throw OrderException::invalidOrderAddressMapping('Invalid type');
            }

            if ($mapping['type'] === self::TYPE_SHIPPING && !isset($mapping['deliveryId'])) {
                throw OrderException::invalidOrderAddressMapping('deliveryId is required for shipping address');
            }

            if ($mapping['type'] === self::TYPE_BILLING) {
                ++$billingAddressCount;
            }
        }

        if ($billingAddressCount > 1) {
            throw OrderException::invalidOrderAddressMapping('Multiple billing addresses provided');
        }
    }
}
