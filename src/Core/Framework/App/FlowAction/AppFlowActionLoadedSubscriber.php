<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\FlowAction;

use Shopware\Core\Framework\App\Aggregate\FlowAction\AppFlowActionEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 *
 * @deprecated tag:v6.6.0 - Will be move to Shopware\Core\Framework\App\Flow\Action
 */
#[Package('core')]
class AppFlowActionLoadedSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', '\Shopware\Core\Framework\App\Flow\Action\AppFlowActionProvider')
        );

        return [
            'app_flow_action.loaded' => 'unserialize',
        ];
    }

    public function unserialize(EntityLoadedEvent $event): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', '\Shopware\Core\Framework\App\Flow\Action\AppFlowActionProvider')
        );

        /** @var AppFlowActionEntity $appFlowAction */
        foreach ($event->getEntities() as $appFlowAction) {
            $iconRaw = $appFlowAction->getIconRaw();

            if ($iconRaw !== null) {
                $appFlowAction->setIcon(base64_encode($iconRaw));
            }
        }
    }
}
