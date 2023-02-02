<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Event;

use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\BusinessEvent;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Validation\DataBag\DataBag;

/**
 * @deprecated tag:v6.5.0 - Will be removed in v6.5.0 Use FlowSendMailActionEvent instead
 */
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
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', 'FlowSendMailActionEvent')
        );

        return $this->businessEvent->getContext();
    }

    public function getDataBag(): DataBag
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', 'FlowSendMailActionEvent')
        );

        return $this->dataBag;
    }

    public function getMailTemplate(): MailTemplateEntity
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', 'FlowSendMailActionEvent')
        );

        return $this->mailTemplate;
    }

    public function getBusinessEvent(): BusinessEvent
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', 'FlowSendMailActionEvent')
        );

        return $this->businessEvent;
    }
}
