<?php declare(strict_types=1);

namespace Shopware\Api\Order\Struct;

use Shopware\Api\Mail\Collection\MailBasicCollection;
use Shopware\Api\Order\Collection\OrderBasicCollection;
use Shopware\Api\Order\Collection\OrderDeliveryBasicCollection;
use Shopware\Api\Order\Collection\OrderStateTranslationBasicCollection;

class OrderStateDetailStruct extends OrderStateBasicStruct
{
    /**
     * @var MailBasicCollection
     */
    protected $mails;

    /**
     * @var OrderBasicCollection
     */
    protected $orders;

    /**
     * @var OrderDeliveryBasicCollection
     */
    protected $orderDeliveries;

    /**
     * @var OrderStateTranslationBasicCollection
     */
    protected $translations;

    public function __construct()
    {
        $this->mails = new MailBasicCollection();

        $this->orders = new OrderBasicCollection();

        $this->orderDeliveries = new OrderDeliveryBasicCollection();

        $this->translations = new OrderStateTranslationBasicCollection();
    }

    public function getMails(): MailBasicCollection
    {
        return $this->mails;
    }

    public function setMails(MailBasicCollection $mails): void
    {
        $this->mails = $mails;
    }

    public function getOrders(): OrderBasicCollection
    {
        return $this->orders;
    }

    public function setOrders(OrderBasicCollection $orders): void
    {
        $this->orders = $orders;
    }

    public function getOrderDeliveries(): OrderDeliveryBasicCollection
    {
        return $this->orderDeliveries;
    }

    public function setOrderDeliveries(OrderDeliveryBasicCollection $orderDeliveries): void
    {
        $this->orderDeliveries = $orderDeliveries;
    }

    public function getTranslations(): OrderStateTranslationBasicCollection
    {
        return $this->translations;
    }

    public function setTranslations(OrderStateTranslationBasicCollection $translations): void
    {
        $this->translations = $translations;
    }
}
