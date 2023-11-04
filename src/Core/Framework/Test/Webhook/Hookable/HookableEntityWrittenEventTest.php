<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Webhook\Hookable;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\AclPrivilegeCollection;
use Shopware\Core\Framework\Webhook\Hookable\HookableEntityWrittenEvent;

/**
 * @internal
 */
class HookableEntityWrittenEventTest extends TestCase
{
    public function testGetter(): void
    {
        $entityId = Uuid::randomHex();
        $event = HookableEntityWrittenEvent::fromWrittenEvent($this->getEntityWrittenEvent($entityId));

        static::assertEquals('product.written', $event->getName());
        static::assertEquals([
            [
                'entity' => 'product',
                'operation' => 'delete',
                'primaryKey' => $entityId,
                'updatedFields' => [],
            ],
        ], $event->getWebhookPayload());
    }

    public function testIsAllowed(): void
    {
        $entityId = Uuid::randomHex();
        $event = HookableEntityWrittenEvent::fromWrittenEvent($this->getEntityWrittenEvent($entityId));

        $allowedPermissions = new AclPrivilegeCollection([
            ProductDefinition::ENTITY_NAME . ':' . AclRoleDefinition::PRIVILEGE_READ,
        ]);
        static::assertTrue($event->isAllowed(
            Uuid::randomHex(),
            $allowedPermissions
        ));

        $notAllowedPermissions = new AclPrivilegeCollection([
            CustomerDefinition::ENTITY_NAME . ':' . AclRoleDefinition::PRIVILEGE_READ,
        ]);
        static::assertFalse($event->isAllowed(
            Uuid::randomHex(),
            $notAllowedPermissions
        ));
    }

    private function getEntityWrittenEvent(string $entityId): EntityWrittenEvent
    {
        $context = Context::createDefaultContext();

        return new EntityWrittenEvent(
            ProductDefinition::ENTITY_NAME,
            [
                new EntityWriteResult(
                    $entityId,
                    [],
                    ProductDefinition::ENTITY_NAME,
                    EntityWriteResult::OPERATION_DELETE,
                    null,
                    null
                ),
            ],
            $context
        );
    }
}
