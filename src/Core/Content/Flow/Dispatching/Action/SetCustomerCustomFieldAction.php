<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Action;

use Shopware\Core\Framework\Event\CustomerAware;
use Shopware\Core\Framework\Event\FlowEvent;

/**
 * @internal (flag:FEATURE_NEXT_17973)
 */
class SetCustomerCustomFieldAction extends FlowAction
{
    public static function getName(): string
    {
        return 'action.set.customer.custom.field';
    }

    public static function getSubscribedEvents(): array
    {
        return [
            self::getName() => 'handle',
        ];
    }

    public function requirements(): array
    {
        return [CustomerAware::class];
    }

    public function handle(FlowEvent $event): void
    {
        // TODO: Implement handle() method.
    }
}
