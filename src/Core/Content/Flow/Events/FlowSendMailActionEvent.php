<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Events;

use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\FlowEvent;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Validation\DataBag\DataBag;

class FlowSendMailActionEvent implements ShopwareEvent
{
    private DataBag $dataBag;

    private MailTemplateEntity $mailTemplate;

    private FlowEvent $flowEvent;

    public function __construct(DataBag $dataBag, MailTemplateEntity $mailTemplate, FlowEvent $flowEvent)
    {
        $this->dataBag = $dataBag;
        $this->mailTemplate = $mailTemplate;
        $this->flowEvent = $flowEvent;
    }

    public function getContext(): Context
    {
        return $this->flowEvent->getContext();
    }

    public function getDataBag(): DataBag
    {
        return $this->dataBag;
    }

    public function getMailTemplate(): MailTemplateEntity
    {
        return $this->mailTemplate;
    }

    public function getFlowEvent(): FlowEvent
    {
        return $this->flowEvent;
    }
}
