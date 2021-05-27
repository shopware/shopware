<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Action;

use Shopware\Core\Framework\Event\FlowEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal (FEATURE_NEXT_8225)
 */
abstract class FlowAction implements EventSubscriberInterface
{
    public const STOP_FLOW = 'action.stop.flow';
    public const ADD_TAG = 'action.add.tag';
    public const REMOVE_TAG = 'action.remove.tag';
    public const SET_ORDER_STATE = 'action.set.order.state';
    public const CALL_WEBHOOK = 'action.call.webhook';

    abstract public function requirements(): array;

    abstract public function getName(): string;

    abstract public function handle(FlowEvent $event): void;
}
