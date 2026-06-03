<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Event;

use Shopware\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Shopware\Core\Content\MailTemplate\Exception\MailEventConfigurationException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\CustomerAware;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Event\ProductAware;
use Shopware\Core\Framework\Event\SalesChannelAware;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Fires when a customer removes a product from their wishlist through the sales
 * channel route. Raw Admin API deletes of customer_wishlist_product are not a customer
 * action and do not fire it.
 */
#[Package('checkout')]
class CustomerWishlistProductRemovedEvent extends Event implements CustomerAware, ProductAware, SalesChannelAware, ScalarValuesAware, FlowEventAware, MailAware
{
    final public const EVENT_NAME = 'customer.wishlist.product.removed';

    public function __construct(
        private readonly SalesChannelContext $salesChannelContext,
        private readonly string $wishlistId,
        private readonly string $productId,
        private readonly string $customerId
    ) {
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('wishlistId', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add(ProductAware::PRODUCT_ID, new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add(CustomerAware::CUSTOMER_ID, new ScalarValueType(ScalarValueType::TYPE_STRING));
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function getWishlistId(): string
    {
        return $this->wishlistId;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function getCustomerId(): string
    {
        return $this->customerId;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelContext->getSalesChannelId();
    }

    public function getMailStruct(): MailRecipientStruct
    {
        throw new MailEventConfigurationException('Data for mailRecipientStruct not available.', self::class);
    }

    /**
     * @return array<string, scalar|array<mixed>|null>
     */
    public function getValues(): array
    {
        return [
            'wishlistId' => $this->wishlistId,
            ProductAware::PRODUCT_ID => $this->productId,
            CustomerAware::CUSTOMER_ID => $this->customerId,
        ];
    }
}
