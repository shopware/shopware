<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Event;

use Shopware\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Fires when an individual promotion code is marked redeemed for an order — for every
 * order producer (Store API checkout, Admin API, sync, import), since redemption itself
 * runs on the order_line_item write (PromotionIndividualCodeRedeemer).
 */
#[Package('checkout')]
class PromotionCodeRedeemedEvent extends Event implements OrderAware, ScalarValuesAware, FlowEventAware, MailAware
{
    final public const EVENT_NAME = 'promotion.code.redeemed';

    public function __construct(
        private readonly Context $context,
        private readonly string $promotionId,
        private readonly string $codeId,
        private readonly string $code,
        private readonly string $orderId,
        private readonly ?string $customerId = null
    ) {
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('promotionId', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('codeId', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('code', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add(OrderAware::ORDER_ID, new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('customerId', new ScalarValueType(ScalarValueType::TYPE_STRING));
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getPromotionId(): string
    {
        return $this->promotionId;
    }

    public function getCodeId(): string
    {
        return $this->codeId;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getCustomerId(): ?string
    {
        return $this->customerId;
    }

    public function getMailStruct(): MailRecipientStruct
    {
        // No recipient is resolved here. Throwing MailEventConfigurationException — the
        // trunk idiom for an event without an inherent recipient — would route through
        // MailStorer's OrderAware fallback, which lazy-loads the order during dispatch.
        // Because this event fires on every order write, that load fails in transient
        // contexts (order recalculation; a migration writing orders before the schema is
        // complete). A flow's send-mail action configures the recipient explicitly.
        return new MailRecipientStruct([]);
    }

    public function getSalesChannelId(): ?string
    {
        return null;
    }

    /**
     * @return array<string, scalar|array<mixed>|null>
     */
    public function getValues(): array
    {
        return [
            'promotionId' => $this->promotionId,
            'codeId' => $this->codeId,
            'code' => $this->code,
            OrderAware::ORDER_ID => $this->orderId,
            'customerId' => $this->customerId,
        ];
    }
}
