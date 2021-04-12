<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Event;

use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\BusinessEvent;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Validation\DataBag\DataBag;

class MailSendSubscriberBridgeEvent implements ShopwareEvent
{
    private DataBag $dataBag;

    private MailTemplateEntity $mailTemplate;

    private BusinessEvent $businessEvent;

    public function __construct(DataBag $dataBag, MailTemplateEntity $mailTemplate, BusinessEvent $businessEvent)
    {
        $this->dataBag = $dataBag;
        $this->mailTemplate = $mailTemplate;
        $this->businessEvent = $businessEvent;
    }

    public function getContext(): Context
    {
        return $this->businessEvent->getContext();
    }

    public function getDataBag(): DataBag
    {
        return $this->dataBag;
    }

    public function getMailTemplate(): MailTemplateEntity
    {
        return $this->mailTemplate;
    }

    public function getBusinessEvent(): BusinessEvent
    {
        return $this->businessEvent;
    }
}
