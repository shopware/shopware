<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal (FEATURE_NEXT_8225)
 */
class FlowCacheClearer implements EventSubscriberInterface
{
    private FlowDispatcher $dispatcher;

    public function __construct(FlowDispatcher $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'flow.written' => 'clearFlowCache',
            'flow_sequence.written' => 'clearFlowCache',
        ];
    }

    public function clearFlowCache(): void
    {
        $this->dispatcher->clearInternalFlowCache();
    }
}
