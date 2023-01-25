<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Hookable;

use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\AclPrivilegeCollection;
use Shopware\Core\Framework\Webhook\Hookable;

#[Package('core')]
class HookableEntityWrittenEvent implements Hookable
{
    private function __construct(private readonly EntityWrittenEvent $event)
    {
    }

    public static function fromWrittenEvent(
        EntityWrittenEvent $event
    ): self {
        return new self($event);
    }

    public function getName(): string
    {
        return $this->event->getName();
    }

    public function getWebhookPayload(): array
    {
        return $this->getPayloadFromEvent($this->event);
    }

    public function isAllowed(string $appId, AclPrivilegeCollection $permissions): bool
    {
        return $permissions->isAllowed($this->event->getEntityName(), AclRoleDefinition::PRIVILEGE_READ);
    }

    public function getPayloadFromEvent(EntityWrittenEvent $event): array
    {
        $payload = [];

        foreach ($event->getWriteResults() as $writeResult) {
            $result = [
                'entity' => $writeResult->getEntityName(),
                'operation' => $writeResult->getOperation(),
                'primaryKey' => $writeResult->getPrimaryKey(),
            ];

            if (!$event instanceof EntityDeletedEvent) {
                $result['updatedFields'] = array_keys($writeResult->getPayload());
            }

            $payload[] = $result;
        }

        return $payload;
    }
}
