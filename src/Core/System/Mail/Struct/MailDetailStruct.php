<?php declare(strict_types=1);

namespace Shopware\System\Mail\Struct;

use Shopware\Checkout\Order\Aggregate\OrderState\Struct\OrderStateBasicStruct;
use Shopware\System\Mail\Aggregate\MailAttachment\Collection\MailAttachmentBasicCollection;
use Shopware\System\Mail\Aggregate\MailTranslation\Collection\MailTranslationBasicCollection;

class MailDetailStruct extends MailBasicStruct
{
    /**
     * @var OrderStateBasicStruct|null
     */
    protected $orderState;

    /**
     * @var \Shopware\System\Mail\Aggregate\MailAttachment\Collection\MailAttachmentBasicCollection
     */
    protected $attachments;

    /**
     * @var MailTranslationBasicCollection
     */
    protected $translations;

    public function __construct()
    {
        $this->attachments = new MailAttachmentBasicCollection();

        $this->translations = new MailTranslationBasicCollection();
    }

    public function getOrderState(): ?\Shopware\Checkout\Order\Aggregate\OrderState\Struct\OrderStateBasicStruct
    {
        return $this->orderState;
    }

    public function setOrderState(?\Shopware\Checkout\Order\Aggregate\OrderState\Struct\OrderStateBasicStruct $orderState): void
    {
        $this->orderState = $orderState;
    }

    public function getAttachments(): MailAttachmentBasicCollection
    {
        return $this->attachments;
    }

    public function setAttachments(MailAttachmentBasicCollection $attachments): void
    {
        $this->attachments = $attachments;
    }

    public function getTranslations(): MailTranslationBasicCollection
    {
        return $this->translations;
    }

    public function setTranslations(MailTranslationBasicCollection $translations): void
    {
        $this->translations = $translations;
    }
}
