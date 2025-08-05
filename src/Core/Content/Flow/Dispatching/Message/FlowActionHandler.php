<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Message;

use Shopware\Core\Content\Flow\Dispatching\Action\FlowAction;
use Shopware\Core\Content\Flow\Dispatching\FlowFactory;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[AsMessageHandler(handles: FlowActionMessage::class)]
#[Package('after-sales')]
final readonly class FlowActionHandler
{
    /**
     * @var array<string, FlowAction>
     */
    private readonly array $actions;

    /**
     * @internal
     */
    public function __construct(
        private readonly FlowFactory $flowFactory,
        $actions
    ) {
        $this->actions = $actions instanceof \Traversable ? iterator_to_array($actions) : $actions;
    }

    public function __invoke(FlowActionMessage $message): void
    {
        $action = $this->actions[$message->sequence->action] ?? null;

        if (!$action instanceof FlowAction) {
            return;
        }

        $event = $message->event;
        $event = $this->flowFactory->restore($event->getName(), $message->context, $event->stored(), $event->data());
        $event->setConfig($message->sequence->config);

        $action->handleFlow($event);
    }
}
