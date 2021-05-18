<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Acl;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\AdminSalesChannelApiSource;
use Shopware\Core\Framework\Api\Exception\MissingPrivilegeException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AclOrderCreateDiscountValidator implements EventSubscriberInterface
{
    public const ACL_ORDER_CREATE_DISCOUNT_PRIVILEGE = 'order:create:discount';

    public static function getSubscribedEvents()
    {
        return [PreWriteValidationEvent::class => 'preValidate'];
    }

    public function preValidate(PreWriteValidationEvent $event): void
    {
        $context = $event->getContext();
        $source = $event->getContext()->getSource();
        if ($source instanceof AdminSalesChannelApiSource) {
            $context = $source->getOriginalContext();
            $source = $context->getSource();
        }

        if ($context->getScope() === Context::SYSTEM_SCOPE || !$source instanceof AdminApiSource || $source->isAdmin()) {
            return;
        }

        $commands = $event->getCommands();
        $missingPrivileges = [];

        foreach ($commands as $command) {
            $resource = $command->getDefinition()->getEntityName();
            $privilege = $command->getPrivilege();

            if ($privilege !== AclRoleDefinition::PRIVILEGE_CREATE || $resource !== OrderLineItemDefinition::ENTITY_NAME) {
                continue;
            }

            $payload = $command->getPayload();
            $type = $payload['type'] ?? null;

            if ($type !== LineItem::CREDIT_LINE_ITEM_TYPE) {
                continue;
            }

            if (!$source->isAllowed(self::ACL_ORDER_CREATE_DISCOUNT_PRIVILEGE)) {
                $missingPrivileges[] = self::ACL_ORDER_CREATE_DISCOUNT_PRIVILEGE;
            }
        }

        $this->tryToThrow($missingPrivileges);
    }

    private function tryToThrow(array $missingPrivileges): void
    {
        if (!empty($missingPrivileges)) {
            throw new MissingPrivilegeException($missingPrivileges);
        }
    }
}
