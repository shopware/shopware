<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-09-17 14:18:33
 */

namespace Shopware\Core\Checkout\Order\SalesChannel\Order;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Response\AbstractResponse;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Order created.
 */
#[Package('checkout')]
final class CreateOrderV2Response extends AbstractResponse
{
    /**
     * Customer comment stored with the order, or `null` when none was supplied.
     */
    public ?string $customerComment;

    /**
     * Affiliate and campaign attribution stored with the order.
     */
    #[Assert\Valid]
    public OrderV2Attribution $attribution;

    /**
     * @internal
     */
    public function __construct(
        /**
         * Identifier of the created order.
         */
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '~^[0-9a-f]{32}$~')]
        public string $id,
        /**
         * Human-readable order number shown to the customer.
         */
        #[Assert\NotBlank]
        public string $orderNumber,
        /**
         * Date and time when the order was placed.
         */
        #[Assert\NotBlank]
        #[Assert\DateTime(format: Defaults::STORAGE_DATE_TIME_FORMAT)]
        public string $createdAt,
        /**
         * Current status of the order workflow.
         */
        #[Assert\NotNull]
        #[Assert\Valid]
        public OrderV2OrderStatus $status,
        /**
         * Customer identity captured when the order was placed.
         */
        #[Assert\NotNull]
        #[Assert\Valid]
        public OrderV2PrivateCustomer|OrderV2BusinessCustomer $customer,
        /**
         * Language persisted with the order and used by translated response fields.
         */
        #[Assert\NotNull]
        #[Assert\Valid]
        public OrderV2Language $language,
        /**
         * Currency and rounding configuration for every monetary amount in the order.
         */
        #[Assert\NotNull]
        #[Assert\Valid]
        public OrderV2Currency $currency,
        /**
         * Calculated monetary totals for the order.
         */
        #[Assert\NotNull]
        #[Assert\Valid]
        public OrderV2PriceSummary $price,
        /**
         * @var list<OrderV2LineItem> Items captured from the cart in their display order.
         */
        #[Assert\NotNull]
        #[Assert\Count(min: 1)]
        #[Assert\Valid]
        public array $lineItems,
        /**
         * Billing address captured when the order was placed.
         */
        #[Assert\NotNull]
        #[Assert\Valid]
        public OrderV2Address $billingAddress,
        /**
         * Primary payment transaction created for the order.
         */
        #[Assert\NotNull]
        #[Assert\Valid]
        public OrderV2Payment $payment,
        /**
         * Primary delivery, or `null` when the order does not require shipping.
         */
        #[Assert\Valid]
        public ?OrderV2Delivery $delivery,
    ) {
        parent::__construct(statusCode: Response::HTTP_CREATED);
    }
}
