<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Action;

use Shopware\Core\Framework\Event\FlowEvent;

/**
 * @internal (FEATURE_NEXT_8225)
 */
class StopFlowAction extends FlowAction
{
    public function getName(): string
    {
        return FlowAction::STOP_FLOW;
    }

    public static function getSubscribedEvents()
    {
        return [
            FlowAction::STOP_FLOW => 'handle',
        ];
    }

    public function requirements(): array
    {
        return [];
    }

    public function handle(FlowEvent $event): void
    {
        $flowState = $event->getFlowState();
        $flowState->stop = true;
    }
}
