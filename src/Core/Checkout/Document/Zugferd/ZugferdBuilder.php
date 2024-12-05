<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Zugferd;

use DateTimeInterface;
use horstoeko\zugferd\ZugferdDocumentBuilder;
use horstoeko\zugferd\ZugferdProfiles;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Document\DocumentConfiguration;
use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('checkout')]
class ZugferdBuilder
{
    /**
     * @internal
     */
    public function __construct(
        protected EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function buildDocument(OrderEntity $order, DocumentGenerateOperation $operation, DocumentConfiguration $config, Context $context): string
    {
        $config->__set('isGross',  match ($order->getTaxStatus()) {
            CartPrice::TAX_STATE_GROSS => true,
            CartPrice::TAX_STATE_NET, CartPrice::TAX_STATE_FREE => false,
            default => throw DocumentException::generationError('Unsupported tax status'),
        });

        /** @var OrderAddressEntity $billingAddress */
        $billingAddress = $order->getAddresses()?->get($order->getBillingAddressId());
        /** @var OrderCustomerEntity $customer */
        $customer = $order->getOrderCustomer();

        /** @var DateTimeInterface $deliveryDate */
        $deliveryDate = $order->getDeliveries()?->first()?->getShippingDateLatest();
        if ($deliveryDate instanceof \DateTimeImmutable) {
            $deliveryDate = \DateTime::createFromImmutable($deliveryDate);
        }

        $document = (new ZugferdDocument(ZugferdDocumentBuilder::createNew(ZugferdProfiles::PROFILE_XRECHNUNG_3), $config))
            ->withBuyerInformation($customer, $billingAddress)
            ->withSellerInformation($config)
            ->withDelivery($order->getDeliveries() ?? new OrderDeliveryCollection())
            ->withTaxes($order->getPrice()->getCalculatedTaxes())
            ->withGeneralOrderData($deliveryDate, $operation->getConfig()['documentDate'] ?? 'now', $config->getDocumentNumber() ?? '', $order->getCurrency()?->getIsoCode() ?? '');

        $this->addLineItems($document, $order->getLineItems());

        $this->eventDispatcher->dispatch(new ZugferdInvoiceGeneratedEvent($document, $order, $config, $context));

        return $document->getContent($order);
    }

    protected function addLineItems(ZugferdDocument $document, ?OrderLineItemCollection $lineItems, string $parentPosition = ''): self
    {
        if (!$lineItems) {
            return $this;
        }

        foreach ($lineItems as $lineItem) {
            $this->matchByType($document, $lineItem, $parentPosition);
            $this->addLineItems($document, $lineItem->getChildren(), $lineItem->getPosition() . '-');
        }

        return $this;
    }

    protected function matchByType(ZugferdDocument $document, OrderLineItemEntity $lineItem, string $parentPosition = ''): void
    {
        match ($lineItem->getType()) {
            LineItem::PRODUCT_LINE_ITEM_TYPE => $document->withProductLineItem($lineItem, $parentPosition),
            LineItem::PROMOTION_LINE_ITEM_TYPE, LineItem::CREDIT_LINE_ITEM_TYPE => $document->withDiscountItem($lineItem),
            default => null,
        };

        $this->eventDispatcher->dispatch(new ZugferdInvoiceItemAddedEvent($document, $lineItem, $parentPosition), 'zugferd-item-added.' . $lineItem->getType());
    }
}
