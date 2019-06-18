<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Entity;

use Shopware\Elasticsearch\Framework\Event\CollectDefinitionsEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SeoUrlSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            CollectDefinitionsEvent::class => 'register',
        ];
    }

    public function register(CollectDefinitionsEvent $event): void
    {
        if (!class_exists('Shopware\Storefront\Framework\Seo\SeoUrl\SeoUrlDefinition')) {
            return;
        }

        $event->add('Shopware\Storefront\Framework\Seo\SeoUrl\SeoUrlDefinition');
    }
}
