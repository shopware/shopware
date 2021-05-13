<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Action;

use Shopware\Core\Framework\Event\BusinessEvent;
use Shopware\Core\Framework\Event\CustomerAware;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Event\SalesChannelAware;
use Shopware\Core\Framework\Event\WebhookAware;

/**
 * @internal (FEATURE_NEXT_8225)
 */
class AddTagAction extends FlowAction
{
    public function __construct()
    {
    }

    public function getName(): string
    {
        return FlowAction::ADD_TAG;
    }

    public static function getSubscribedEvents()
    {
        return [
            FlowAction::ADD_TAG => 'addTag',
        ];
    }

    public function requirements(): array
    {
        return [OrderAware::class, CustomerAware::class, WebhookAware::class, SalesChannelAware::class];
    }

    public function addTag(BusinessEvent $event): void
    {
        //TODO
    }
}
