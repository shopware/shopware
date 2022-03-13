<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Event;

use Shopware\Core\Framework\Event\FlowEvent;
use Shopware\Core\Framework\Webhook\AclPrivilegeCollection;
use Shopware\Core\Framework\Webhook\Hookable;
use Symfony\Contracts\EventDispatcher\Event;

class AppFlowActionEvent extends Event implements Hookable
{
    public const PREFIX = 'app_flow_action.';

    private FlowEvent $flowEvent;

    private string $appFlowActionId;

    public function __construct(string $appFlowActionId, FlowEvent $flowEvent)
    {
        $this->appFlowActionId = $appFlowActionId;
        $this->flowEvent = $flowEvent;
    }

    public function getAppFlowActionId(): string
    {
        return $this->appFlowActionId;
    }

    public function getEvent(): FlowEvent
    {
        return $this->flowEvent;
    }

    public function getName(): string
    {
        return $this->flowEvent->getActionName();
    }

    public function getWebhookPayload(): array
    {
        return [];
    }

    /**
     * Apps don't need special ACL permissions for action, so this function always return true
     */
    public function isAllowed(string $appId, AclPrivilegeCollection $permissions): bool
    {
        return true;
    }
}
