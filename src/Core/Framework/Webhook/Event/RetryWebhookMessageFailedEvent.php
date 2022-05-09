<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\MessageQueue\DeadMessage\DeadMessageEntity;

class RetryWebhookMessageFailedEvent
{
    private DeadMessageEntity $deadMessage;

    private Context $context;

    public function __construct(DeadMessageEntity $deadMessage, Context $context)
    {
        $this->deadMessage = $deadMessage;
        $this->context = $context;
    }

    public function getDeadMessage(): DeadMessageEntity
    {
        return $this->deadMessage;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
