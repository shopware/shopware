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
    public const ADD_ORDER_TAG = 'action.add.order.tag';
    public const ADD_CUSTOMER_TAG = 'action.add.customer.tag';
    public const REMOVE_ORDER_TAG = 'action.remove.order.tag';
    public const REMOVE_CUSTOMER_TAG = 'action.remove.customer.tag';
    public const SET_ORDER_STATE = 'action.set.order.state';
    public const SEND_MAIL = 'action.mail.send';
    public const CALL_WEBHOOK = 'action.call.webhook';
    public const GENERATE_DOCUMENT = 'action.generate.document';

    abstract public function requirements(): array;

    abstract public function getName(): string;

    abstract public function handle(FlowEvent $event): void;
}
