<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Action;

use Shopware\Core\Framework\Event\FlowEvent;
use Shopware\Core\Framework\Event\OrderAware;

/**
 * @internal (FEATURE_NEXT_8225)
 */
class RemoveTagAction extends FlowAction
{
    public function getName(): string
    {
        return FlowAction::REMOVE_ORDER_TAG;
    }

    public static function getSubscribedEvents()
    {
        return [
            FlowAction::REMOVE_ORDER_TAG => 'handle',
        ];
    }

    public function requirements(): array
    {
        return [OrderAware::class];
    }

    public function handle(FlowEvent $event): void
    {
        //TODO TBD
    }
}
