<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Events;

use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\FlowEvent;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Validation\DataBag\DataBag;

class FlowSendMailActionEvent implements ShopwareEvent
{
    private DataBag $dataBag;

    private MailTemplateEntity $mailTemplate;

    /**
     * @deprecated tag:v6.5.0 Will be removed
     */
    private FlowEvent $flowEvent;

    /**
     * @deprecated tag:v6.5.0 Will be StorableFlow type
     */
    private ?StorableFlow $flow = null;

    /**
     * @param FlowEvent|StorableFlow $event
     */
    public function __construct(DataBag $dataBag, MailTemplateEntity $mailTemplate, $event)
    {
        $this->dataBag = $dataBag;
        $this->mailTemplate = $mailTemplate;

        if ($event instanceof StorableFlow) {
            $this->flow = $event;
            if (!Feature::isActive('v6.5.0.0') && $event->getFlowEvent()) {
                $this->flowEvent = $event->getFlowEvent();
            }
        }

        /** @deprecated tag:v6.5.0 Will be removed */
        if ($event instanceof FlowEvent) {
            $this->flowEvent = $event;
        }
    }

    public function getContext(): Context
    {
        if ($this->flow) {
            return $this->flow->getContext();
        }

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

    /**
     * @deprecated tag:v6.5.0 Will be removed
     */
    public function getFlowEvent(): FlowEvent
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );

        return $this->flowEvent;
    }

    public function getStorableFlow(): ?StorableFlow
    {
        return $this->flow;
    }
}
