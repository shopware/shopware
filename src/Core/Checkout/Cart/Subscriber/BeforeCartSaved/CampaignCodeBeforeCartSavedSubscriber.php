<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Subscriber\BeforeCartSaved;

use Shopware\Core\Checkout\Cart\Event\BeforeCartSavedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CampaignCodeBeforeCartSavedSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            BeforeCartSavedEvent::class => 'needsSaving',
        ];
    }

    public function needsSaving(BeforeCartSavedEvent $event): void
    {
        if ($event->getCart()->getCampaignCode() === null) {
            return;
        }

        $event->needsSaving();
    }
}
