<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Action;

use Shopware\Core\Framework\Event\BusinessEvent;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Event\WebhookAware;

/**
 * @internal (FEATURE_NEXT_8225)
 */
class RemoveTagAction extends FlowAction
{
    public function getName(): string
    {
        return FlowAction::REMOVE_TAG;
    }

    public static function getSubscribedEvents()
    {
        return [
            FlowAction::REMOVE_TAG => 'removeTag',
        ];
    }

    public function requirements(): array
    {
        return [OrderAware::class, WebhookAware::class];
    }

    public function removeTag(BusinessEvent $event): void
    {
        //TODO TBD
    }
}
