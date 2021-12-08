<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\FlowAction;

use Shopware\Core\Framework\App\Aggregate\FlowAction\AppFlowActionEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal (flag:FEATURE_NEXT_17540) - only for use by the app-system
 */
class AppFlowActionLoadedSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'app_flow_action.loaded' => 'unserialize',
        ];
    }

    public function unserialize(EntityLoadedEvent $event): void
    {
        /** @var AppFlowActionEntity $appFlowAction */
        foreach ($event->getEntities() as $appFlowAction) {
            $iconRaw = $appFlowAction->getIconRaw();

            if ($iconRaw !== null) {
                $appFlowAction->setIcon(base64_encode($iconRaw));
            }
        }
    }
}
