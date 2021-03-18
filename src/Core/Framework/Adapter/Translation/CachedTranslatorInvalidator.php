<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Translation;

use Shopware\Core\Framework\Adapter\Cache\CacheInvalidationLogger;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\System\Snippet\SnippetDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CachedTranslatorInvalidator implements EventSubscriberInterface
{
    private CacheInvalidationLogger $logger;

    public function __construct(CacheInvalidationLogger $logger)
    {
        $this->logger = $logger;
    }

    public static function getSubscribedEvents()
    {
        return [
            EntityWrittenContainerEvent::class => [
                ['invalidate', 2000],
            ],
        ];
    }

    public function invalidate(EntityWrittenContainerEvent $event): void
    {
        $snippets = $event->getEventByEntityName(SnippetDefinition::ENTITY_NAME);

        if (!$snippets) {
            return;
        }

        $tags = [];
        foreach ($snippets->getPayloads() as $payload) {
            if (isset($payload['translationKey'])) {
                $tags[] = Translator::buildName($payload['translationKey']);
            }
        }
        $this->logger->log($tags);
    }
}
