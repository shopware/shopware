<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\MessageQueue\DeadMessage\DeadMessageEntity;

/**
 * @internal (flag:FEATURE_NEXT_14363) only for use by the app-system
 */
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
