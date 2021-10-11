<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Action;

use Shopware\Core\Framework\Event\FlowEvent;
use Shopware\Core\Framework\Event\OrderAware;

/**
 * @internal (flag:FEATURE_NEXT_17973)
 */
class SetOrderCustomFieldAction extends FlowAction
{
    public static function getName(): string
    {
        return 'action.set.order.custom.field';
    }

    public static function getSubscribedEvents(): array
    {
        return [
            self::getName() => 'handle',
        ];
    }

    public function requirements(): array
    {
        return [OrderAware::class];
    }

    public function handle(FlowEvent $event): void
    {
        // TODO: Implement handle() method.
    }
}
