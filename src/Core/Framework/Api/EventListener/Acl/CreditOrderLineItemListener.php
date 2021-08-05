<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\EventListener\Acl;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Framework\Api\Acl\Event\CommandAclValidationEvent;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CreditOrderLineItemListener implements EventSubscriberInterface
{
    public const ACL_ORDER_CREATE_DISCOUNT_PRIVILEGE = 'order:create:discount';

    public static function getSubscribedEvents()
    {
        return [CommandAclValidationEvent::class => 'validate'];
    }

    public function validate(CommandAclValidationEvent $event): void
    {
        $command = $event->getCommand();
        $resource = $command->getDefinition()->getEntityName();
        $privilege = $command->getPrivilege();

        if ($privilege !== AclRoleDefinition::PRIVILEGE_CREATE || $resource !== OrderLineItemDefinition::ENTITY_NAME) {
            return;
        }

        $payload = $command->getPayload();
        $type = $payload['type'] ?? null;

        if ($type !== LineItem::CREDIT_LINE_ITEM_TYPE) {
            return;
        }

        if (!$event->getSource()->isAllowed(self::ACL_ORDER_CREATE_DISCOUNT_PRIVILEGE)) {
            $event->addMissingPrivilege(self::ACL_ORDER_CREATE_DISCOUNT_PRIVILEGE);
        }
    }
}
