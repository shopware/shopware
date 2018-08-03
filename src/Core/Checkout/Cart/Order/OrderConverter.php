<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Order;

use Shopware\Core\Checkout\Cart\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPosition;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Tax\TaxDetector;
use Shopware\Core\Checkout\Cart\Transaction\Struct\Transaction;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressStruct;
use Shopware\Core\Checkout\Order\Exception\CustomerHasNoActiveBillingAddressException;
use Shopware\Core\Checkout\Order\Exception\DeliveryWithoutAddressException;
use Shopware\Core\Checkout\Order\Exception\EmptyCartException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Struct\Uuid;

class OrderConverter
{
    /**
     * @var TaxDetector
     */
    private $taxDetector;

    public function __construct(TaxDetector $taxDetector)
    {
        $this->taxDetector = $taxDetector;
    }

    /**
     * @throws CustomerHasNoActiveBillingAddressException
     * @throws CustomerNotLoggedInException
     * @throws DeliveryWithoutAddressException
     * @throws EmptyCartException
     */
    public function convert(Cart $cart, CheckoutContext $context): array
    {
        $addressId = Uuid::uuid4()->getHex();
        if (!$context->getCustomer()) {
            throw new CustomerNotLoggedInException();
        }
        if ($cart->getLineItems()->count() <= 0) {
            throw new EmptyCartException();
        }

        if (!$context->getCustomer()->getActiveBillingAddress()) {
            throw new CustomerHasNoActiveBillingAddressException($context->getCustomer()->getId());
        }

        /** @var Delivery $delivery */
        foreach ($cart->getDeliveries() as $delivery) {
            if (!$delivery->getLocation()->getAddress()) {
                throw new DeliveryWithoutAddressException();
            }
        }

        $data = [
            'id' => Uuid::uuid4()->getHex(),
            'date' => (new \DateTime())->format(Defaults::DATE_FORMAT),
            'amountTotal' => $cart->getPrice()->getTotalPrice(),
            'amountNet' => $cart->getPrice()->getNetPrice(),
            'positionPrice' => $cart->getPrice()->getPositionPrice(),
            'shippingTotal' => $cart->getShippingCosts()->getTotalPrice(),
            'shippingNet' => $cart->getShippingCosts()->getTotalPrice() - $cart->getShippingCosts()->getCalculatedTaxes()->getAmount(),
            'isNet' => !$this->taxDetector->useGross($context),
            'isTaxFree' => $this->taxDetector->isNetDelivery($context),
            'customerId' => $context->getCustomer()->getId(),
            'stateId' => Defaults::ORDER_STATE_OPEN,
            'paymentMethodId' => $context->getPaymentMethod()->getId(),
            'currencyId' => $context->getCurrency()->getId(),
            'salesChannelId' => $context->getSalesChannel()->getId(),
            'billingAddressId' => $addressId,
            'lineItems' => [],
            'deliveries' => [],
        ];

        $address = $context->getCustomer()->getActiveBillingAddress();

        $data['billingAddress'] = $this->convertAddress($address);
        $data['billingAddress']['id'] = $addressId;

        $lineItems = [];
        foreach ($cart->getLineItems() as $lineItem) {
            $row = $this->convertLineItem($lineItem);
            $row['id'] = Uuid::uuid4()->getHex();
            $lineItems[$lineItem->getKey()] = $row;
        }

        /** @var Delivery $delivery */
        foreach ($cart->getDeliveries() as $delivery) {
            $data['deliveries'][] = $this->convertDelivery($delivery, $lineItems);
        }

        $lineItems = array_values($lineItems);

        foreach ($lineItems as $parent) {
            $lineItem = $cart->getLineItems()->get($parent['identifier']);
            if (!$lineItem->getChildren()) {
                continue;
            }

            $children = $this->convertNestedLineItem($lineItem, $parent['id']);
            foreach ($children as $child) {
                $lineItems[] = $child;
            }
        }

        foreach ($cart->getTransactions() as $transaction) {
            $data['transactions'][] = $this->convertTransaction($transaction);
        }

        $data['lineItems'] = $lineItems;

        return $data;
    }

    private function convertNestedLineItem(LineItem $lineItem, string $parentId = null)
    {
        $children = $lineItem->getChildren();

        if (!$lineItem->hasChildren()) {
            return [];
        }

        $data = [];
        foreach ($children as $child) {
            $row = $this->convertLineItem($child);
            $row['parentId'] = $parentId;
            $row['id'] = Uuid::uuid4()->getHex();
            $data[] = $row;

            if (!$child->hasChildren()) {
                continue;
            }

            $nested = $this->convertNestedLineItem($lineItem, $row['id']);
            foreach ($nested as $subChild) {
                $data[] = $subChild;
            }
        }

        return $data;
    }

    private function convertAddress(CustomerAddressStruct $address): array
    {
        return array_filter([
            'company' => $address->getCompany(),
            'department' => $address->getDepartment(),
            'salutation' => $address->getSalutation(),
            'title' => $address->getTitle(),
            'firstName' => $address->getFirstName(),
            'lastName' => $address->getLastName(),
            'street' => $address->getStreet(),
            'zipcode' => $address->getZipcode(),
            'city' => $address->getCity(),
            'vatId' => $address->getVatId(),
            'phoneNumber' => $address->getPhoneNumber(),
            'additionalAddressLine1' => $address->getAdditionalAddressLine1(),
            'additionalAddressLine2' => $address->getAdditionalAddressLine2(),
            'countryId' => $address->getCountryId(),
            'countryStateId' => $address->getCountryStateId(),
        ]);
    }

    private function convertLineItem(LineItem $lineItem): array
    {
        return [
            'identifier' => $lineItem->getKey(),
            'quantity' => $lineItem->getQuantity(),
            'unitPrice' => $lineItem->getPrice()->getUnitPrice(),
            'totalPrice' => $lineItem->getPrice()->getTotalPrice(),
            'type' => $lineItem->getType(),
            'label' => $lineItem->getLabel(),
            'description' => $lineItem->getDescription(),
        ];
    }

    /**
     * @param Delivery $delivery
     * @param array[]  $lineItems
     *
     * @return array
     */
    private function convertDelivery(Delivery $delivery, array $lineItems): array
    {
        $deliveryData = [
            'shippingDateEarliest' => $delivery->getDeliveryDate()->getEarliest()->format(Defaults::DATE_FORMAT),
            'shippingDateLatest' => $delivery->getDeliveryDate()->getLatest()->format(Defaults::DATE_FORMAT),
            'shippingMethodId' => $delivery->getShippingMethod()->getId(),
            'shippingAddress' => $this->convertAddress($delivery->getLocation()->getAddress()),
            'orderStateId' => Defaults::ORDER_STATE_OPEN,
            'positions' => [],
        ];

        /** @var DeliveryPosition $position */
        foreach ($delivery->getPositions() as $position) {
            $deliveryData['positions'][] = [
                'unitPrice' => $position->getPrice()->getUnitPrice(),
                'totalPrice' => $position->getPrice()->getTotalPrice(),
                'quantity' => $position->getQuantity(),
                'orderLineItemId' => $lineItems[$position->getIdentifier()]['id'],
            ];
        }

        return $deliveryData;
    }

    private function convertTransaction(Transaction $transaction): array
    {
        return [
            'paymentMethodId' => $transaction->getPaymentMethodId(),
            'amount' => $transaction->getAmount(),
            'orderTransactionStateId' => Defaults::ORDER_TRANSACTION_OPEN,
        ];
    }
}
