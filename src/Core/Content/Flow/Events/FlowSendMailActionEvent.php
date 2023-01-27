<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Events;

use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\DataBag;

#[Package('business-ops')]
class FlowSendMailActionEvent implements ShopwareEvent
{
    public function __construct(
        private readonly DataBag $dataBag,
        private readonly MailTemplateEntity $mailTemplate,
        private readonly StorableFlow $flow
    ) {
    }

    public function getContext(): Context
    {
        return $this->flow->getContext();
    }

    public function getDataBag(): DataBag
    {
        return $this->dataBag;
    }

    public function getMailTemplate(): MailTemplateEntity
    {
        return $this->mailTemplate;
    }

    public function getStorableFlow(): StorableFlow
    {
        return $this->flow;
    }
}
