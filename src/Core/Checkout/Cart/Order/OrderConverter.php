<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Order;

use DateTime;
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
use Shopware\Core\Framework\Util\Random;

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
        $deepLinkCode = Random::getBase64UrlString(32);

        $cartPrice = $cart->getPrice();
        $cartShippingCosts = $cart->getShippingCosts();
        $cartShippingCostsTotalPrice = $cartShippingCosts->getTotalPrice();
        $data = [
            'id' => Uuid::uuid4()->getHex(),
            'date' => (new DateTime())->format(Defaults::DATE_FORMAT),
            'amountTotal' => $cartPrice->getTotalPrice(),
            'amountNet' => $cartPrice->getNetPrice(),
            'positionPrice' => $cartPrice->getPositionPrice(),
            'shippingTotal' => $cartShippingCostsTotalPrice,
            'shippingNet' => $cartShippingCostsTotalPrice - $cartShippingCosts->getCalculatedTaxes()->getAmount(),
            'isNet' => !$this->taxDetector->useGross($context),
            'isTaxFree' => $this->taxDetector->isNetDelivery($context),
            'stateId' => Defaults::ORDER_STATE_OPEN,
            'paymentMethodId' => $context->getPaymentMethod()->getId(),
            'currencyId' => $context->getCurrency()->getId(),
            'salesChannelId' => $context->getSalesChannel()->getId(),
            'billingAddressId' => $addressId,
            'lineItems' => [],
            'deliveries' => [],
            'deepLinkCode' => $deepLinkCode,
        ];

        $address = $context->getCustomer()->getActiveBillingAddress();

        $data['orderCustomer'] = $this->convertCustomer($context);

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

    private function convertNestedLineItem(LineItem $lineItem, string $parentId = null): array
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
        $lineItemPrice = $lineItem->getPrice();

        return [
            'identifier' => $lineItem->getKey(),
            'quantity' => $lineItem->getQuantity(),
            'unitPrice' => $lineItemPrice->getUnitPrice(),
            'totalPrice' => $lineItemPrice->getTotalPrice(),
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
            $positionPrice = $position->getPrice();
            $deliveryData['positions'][] = [
                'unitPrice' => $positionPrice->getUnitPrice(),
                'totalPrice' => $positionPrice->getTotalPrice(),
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

    private function convertCustomer(CheckoutContext $context): array
    {
        $customer = $context->getCustomer();

        return [
            'customerId' => $customer->getId(),
            'email' => $customer->getEmail(),
            'firstName' => $customer->getFirstName(),
            'lastName' => $customer->getLastName(),
            'salutation' => $customer->getSalutation(),
            'title' => $customer->getTitle(),
            'customerNumber' => $customer->getCustomerNumber(),
        ];
    }
}
