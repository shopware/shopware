<?php declare(strict_types=1);
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\CartBridge\Order;

use Ramsey\Uuid\Uuid;
use Shopware\Cart\Cart\Struct\CalculatedCart;
use Shopware\Cart\Delivery\Struct\Delivery;
use Shopware\Cart\Delivery\Struct\DeliveryPosition;
use Shopware\Cart\LineItem\CalculatedLineItemInterface;
use Shopware\Cart\Order\OrderPersisterInterface;
use Shopware\Cart\Tax\TaxDetector;
use Shopware\Context\Struct\ShopContext;
use Shopware\Customer\Struct\CustomerAddressBasicStruct;
use Shopware\Order\Repository\OrderRepository;

class OrderPersister implements OrderPersisterInterface
{
    /**
     * @var OrderRepository
     */
    private $repository;

    /**
     * @var TaxDetector
     */
    private $taxDetector;

    public function __construct(OrderRepository $repository, TaxDetector $taxDetector)
    {
        $this->repository = $repository;
        $this->taxDetector = $taxDetector;
    }

    public function persist(CalculatedCart $calculatedCart, ShopContext $context): void
    {
        $order = $this->convert($calculatedCart, $context);

        $this->repository->create([$order], $context->getTranslationContext());
    }

    private function convert(CalculatedCart $calculatedCart, ShopContext $context): array
    {
        $addressUuid = Uuid::uuid4()->toString();

        $data = [
            'date' => (new \DateTime())->format('Y-m-d H:i:s'),
            'amountTotal' => $calculatedCart->getPrice()->getTotalPrice(),
            'amountNet' => $calculatedCart->getPrice()->getNetPrice(),
            'positionPrice' => $calculatedCart->getPrice()->getPositionPrice(),
            'shippingTotal' => $calculatedCart->getShippingCosts()->getTotalPrice(),
            'shippingNet' => $calculatedCart->getShippingCosts()->getTotalPrice() - $calculatedCart->getShippingCosts()->getCalculatedTaxes()->getAmount(),
            'isNet' => !$this->taxDetector->useGross($context),
            'isTaxFree' => $this->taxDetector->isNetDelivery($context),
            'customerUuid' => $context->getCustomer()->getUuid(),
            'stateUuid' => 'SWAG-ORDER-STATE-UUID-0',
            'paymentMethodUuid' => $context->getPaymentMethod()->getUuid(),
            'currencyUuid' => $context->getCurrency()->getUuid(),
            'shopUuid' => $context->getShop()->getUuid(),
            'billingAddressUuid' => $addressUuid,
            'lineItems' => [],
            'deliveries' => [],
            'context' => json_encode($context),
            'payload' => json_encode($calculatedCart),
        ];

        $address = $context->getCustomer()->getActiveBillingAddress();

        $data['billingAddress'] = $this->convertAddress($address);
        $data['billingAddress']['uuid'] = $addressUuid;

        $lineItemMap = [];
        /** @var CalculatedLineItemInterface $lineItem */
        foreach ($calculatedCart->getCalculatedLineItems() as $lineItem) {
            $uuid = Uuid::uuid4()->toString();
            $lineItemMap[$lineItem->getIdentifier()] = $uuid;

            $data['lineItems'][] = [
                'uuid' => $uuid,
                'identifier' => $lineItem->getIdentifier(),
                'quantity' => $lineItem->getQuantity(),
                'unitPrice' => $lineItem->getPrice()->getUnitPrice(),
                'totalPrice' => $lineItem->getPrice()->getTotalPrice(),
                'type' => $lineItem->getType(),
                'payload' => json_encode($lineItem),
            ];
        }

        /** @var Delivery $delivery */
        foreach ($calculatedCart->getDeliveries() as $delivery) {
            $deliveryData = [
                'shippingDateEarliest' => $delivery->getDeliveryDate()->getEarliest()->format('Y-m-d H:i:s'),
                'shippingDateLatest' => $delivery->getDeliveryDate()->getLatest()->format('Y-m-d H:i:s'),
                'shippingMethodUuid' => $delivery->getShippingMethod()->getUuid(),
                'shippingAddress' => $this->convertAddress($delivery->getLocation()->getAddress()),
                'orderStateUuid' => 'SWAG-ORDER-STATE-UUID-0',
                'positions' => [],
                'payload' => json_encode($delivery),
            ];

            /** @var DeliveryPosition $position */
            foreach ($delivery->getPositions() as $position) {
                $deliveryData['positions'][] = [
                    'unitPrice' => $position->getPrice()->getUnitPrice(),
                    'totalPrice' => $position->getPrice()->getTotalPrice(),
                    'quantity' => $position->getQuantity(),
                    'payload' => json_encode($position),
                    'orderLineItemUuid' => $lineItemMap[$position->getIdentifier()],
                ];
            }

            $data['deliveries'][] = $deliveryData;
        }

        return $data;
    }

    private function convertAddress(CustomerAddressBasicStruct $address): array
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
            'countryUuid' => $address->getCountryUuid(),
            'countryStateUuid' => $address->getCountryStateUuid(),
        ]);
    }
}
