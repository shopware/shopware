<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Hookable;

use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Event\BusinessEventInterface;
use Shopware\Core\Framework\Event\EventData\ArrayType;
use Shopware\Core\Framework\Event\EventData\EntityCollectionType;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\ObjectType;
use Shopware\Core\Framework\Webhook\AclPrivilegeCollection;
use Shopware\Core\Framework\Webhook\BusinessEventEncoder;
use Shopware\Core\Framework\Webhook\Hookable;

class HookableBusinessEvent implements Hookable
{
    /**
     * @var BusinessEventInterface
     */
    private $businessEvent;

    /**
     * @var BusinessEventEncoder
     */
    private $businessEventEncoder;

    private function __construct(BusinessEventInterface $businessEvent, BusinessEventEncoder $businessEventEncoder)
    {
        $this->businessEvent = $businessEvent;
        $this->businessEventEncoder = $businessEventEncoder;
    }

    public static function fromBusinessEvent(
        BusinessEventInterface $businessEvent,
        BusinessEventEncoder $businessEventEncoder
    ): self {
        return new self($businessEvent, $businessEventEncoder);
    }

    public function getName(): string
    {
        return $this->businessEvent->getName();
    }

    public function getWebhookPayload(): array
    {
        return $this->businessEventEncoder->encode($this->businessEvent);
    }

    public function isAllowed(string $appId, AclPrivilegeCollection $permissions): bool
    {
        foreach ($this->businessEvent::getAvailableData()->toArray() as $dataType) {
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
