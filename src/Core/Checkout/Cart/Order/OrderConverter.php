<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Order;

use Shopware\Core\Checkout\Cart\Cart\Struct\CalculatedCart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPosition;
use Shopware\Core\Checkout\Cart\LineItem\CalculatedLineItemInterface;
use Shopware\Core\Checkout\Cart\LineItem\NestedInterface;
use Shopware\Core\Checkout\Cart\Tax\TaxDetector;
use Shopware\Core\Checkout\Cart\Transaction\Struct\Transaction;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressStruct;
use Shopware\Core\Checkout\Order\Exception\CustomerHasNoActiveBillingAddressException;
use Shopware\Core\Checkout\Order\Exception\CustomerNotLoggedInException;
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

    public function convert(CalculatedCart $calculatedCart, CheckoutContext $context): array
    {
        $addressId = Uuid::uuid4()->getHex();
        if (!$context->getCustomer()) {
            throw new CustomerNotLoggedInException();
        }
        if ($calculatedCart->getCalculatedLineItems()->count() <= 0) {
            throw new EmptyCartException();
        }

        if (!$context->getCustomer()->getActiveBillingAddress()) {
            throw new CustomerHasNoActiveBillingAddressException($context->getCustomer()->getId());
        }

        /** @var Delivery $delivery */
        foreach ($calculatedCart->getDeliveries() as $delivery) {
            if (!$delivery->getLocation()->getAddress()) {
                throw new DeliveryWithoutAddressException();
            }
        }

        $data = [
            'id' => Uuid::uuid4()->getHex(),
            'date' => (new \DateTime())->format('Y-m-d H:i:s'),
            'amountTotal' => $calculatedCart->getPrice()->getTotalPrice(),
            'amountNet' => $calculatedCart->getPrice()->getNetPrice(),
            'positionPrice' => $calculatedCart->getPrice()->getPositionPrice(),
            'shippingTotal' => $calculatedCart->getShippingCosts()->getTotalPrice(),
            'shippingNet' => $calculatedCart->getShippingCosts()->getTotalPrice() - $calculatedCart->getShippingCosts()->getCalculatedTaxes()->getAmount(),
            'isNet' => !$this->taxDetector->useGross($context),
            'isTaxFree' => $this->taxDetector->isNetDelivery($context),
            'customerId' => $context->getCustomer()->getId(),
            'stateId' => Defaults::ORDER_STATE_OPEN,
            'paymentMethodId' => $context->getPaymentMethod()->getId(),
            'currencyId' => $context->getCurrency()->getId(),
            'touchpointId' => $context->getTouchpoint()->getId(),
            'billingAddressId' => $addressId,
            'lineItems' => [],
            'deliveries' => [],
        ];

        $address = $context->getCustomer()->getActiveBillingAddress();

        $data['billingAddress'] = $this->convertAddress($address);
        $data['billingAddress']['id'] = $addressId;

        $lineItems = [];
        foreach ($calculatedCart->getCalculatedLineItems() as $lineItem) {
            $row = $this->convertLineItem($lineItem);
            $row['id'] = Uuid::uuid4()->getHex();
            $lineItems[$lineItem->getIdentifier()] = $row;
        }

        /** @var Delivery $delivery */
        foreach ($calculatedCart->getDeliveries() as $delivery) {
            $data['deliveries'][] = $this->convertDelivery($delivery, $lineItems);
        }

        $lineItems = array_values($lineItems);

        foreach ($lineItems as $parent) {
            $lineItem = $calculatedCart->getCalculatedLineItems()->get($parent['identifier']);
            if (!$lineItem instanceof NestedInterface) {
                continue;
            }

            $children = $this->convertNestedLineItem($lineItem, $parent['id']);
            foreach ($children as $child) {
                $lineItems[] = $child;
            }
        }

        foreach ($calculatedCart->getTransactions() as $transaction) {
            $data['transactions'][] = $this->convertTransaction($transaction);
        }

        $data['lineItems'] = $lineItems;

        return $data;
    }

    private function convertNestedLineItem(NestedInterface $lineItem, string $parentId = null)
    {
        $data = [];
        foreach ($lineItem->getChildren() as $child) {
            $row = $this->convertLineItem($child);
            $row['parentId'] = $parentId;
            $row['id'] = Uuid::uuid4()->getHex();
            $data[] = $row;

            if (!$child instanceof NestedInterface) {
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

    private function convertLineItem(CalculatedLineItemInterface $lineItem): array
    {
        return [
            'identifier' => $lineItem->getIdentifier(),
            'quantity' => $lineItem->getQuantity(),
            'unitPrice' => $lineItem->getPrice()->getUnitPrice(),
            'totalPrice' => $lineItem->getPrice()->getTotalPrice(),
            'type' => $lineItem->getType(),
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
            'shippingDateEarliest' => $delivery->getDeliveryDate()->getEarliest()->format('Y-m-d H:i:s'),
            'shippingDateLatest' => $delivery->getDeliveryDate()->getLatest()->format('Y-m-d H:i:s'),
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
