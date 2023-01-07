<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Events;

use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Validation\DataBag\DataBag;

/**
 * @package business-ops
 */
class FlowSendMailActionEvent implements ShopwareEvent
{
    private DataBag $dataBag;

    private MailTemplateEntity $mailTemplate;

    private StorableFlow $flow;

    public function __construct(DataBag $dataBag, MailTemplateEntity $mailTemplate, StorableFlow $flow)
    {
        $this->dataBag = $dataBag;
        $this->mailTemplate = $mailTemplate;
        $this->flow = $flow;
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
