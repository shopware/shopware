<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Event;

use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Event\EventData\ArrayType;
use Shopware\Core\Framework\Event\EventData\EntityCollectionType;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\ObjectType;
use Shopware\Core\Framework\Event\FlowEvent;
use Shopware\Core\Framework\Webhook\AclPrivilegeCollection;
use Shopware\Core\Framework\Webhook\Hookable;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal only for use by the app-system
 */
class AppFlowActionEvent extends Event implements Hookable
{
    public const PREFIX = 'app_flow_action.';

    private FlowEvent $flowEvent;

    public function __construct(FlowEvent $flowEvent)
    {
        $this->flowEvent = $flowEvent;
    }

    public function getEvent(): FlowEvent
    {
        return $this->flowEvent;
    }

    public function getName(): string
    {
        return self::PREFIX . $this->flowEvent->getActionName();
    }

    public function getWebhookPayload(): array
    {
        return [];
    }

    public function isAllowed(string $appId, AclPrivilegeCollection $permissions): bool
    {
        foreach ($this->flowEvent->getEvent()::getAvailableData()->toArray() as $dataType) {
            if (!$this->checkPermissionsForDataType($dataType, $permissions)) {
                return false;
            }
        }

        return true;
    }

    private function checkPermissionsForDataType(array $dataType, AclPrivilegeCollection $permissions): bool
    {
        if ($dataType['type'] === ObjectType::TYPE && \is_array($dataType['data']) && !empty($dataType['data'])) {
            foreach ($dataType['data'] as $nested) {
                if (!$this->checkPermissionsForDataType($nested, $permissions)) {
                    return false;
                }
            }
        }

        if ($dataType['type'] === ArrayType::TYPE && $dataType['of']) {
            if (!$this->checkPermissionsForDataType($dataType['of'], $permissions)) {
                return false;
            }
        }

        if ($dataType['type'] === EntityType::TYPE || $dataType['type'] === EntityCollectionType::TYPE) {
            /** @var EntityDefinition $definition */
            $definition = new $dataType['entityClass']();
            if (!$permissions->isAllowed($definition->getEntityName(), AclRoleDefinition::PRIVILEGE_READ)) {
                return false;
            }
        }

        return true;
    }
}
