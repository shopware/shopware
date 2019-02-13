<?php declare(strict_types=1);

namespace Shopware\Core\Content\Rule\DataAbstractionLayer;

use Shopware\Core\Content\Rule\DataAbstractionLayer\Indexing\RulePayloadIndexer;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Content\Rule\RuleEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class RulePayloadSubscriber implements EventSubscriberInterface
{
    /**
     * @var RulePayloadIndexer
     */
    private $indexer;

    public function __construct(RulePayloadIndexer $indexer)
    {
        $this->indexer = $indexer;
    }

    public static function getSubscribedEvents()
    {
        return [
            RuleEvents::RULE_LOADED_EVENT => 'unserialize',
        ];
    }

    public function unserialize(EntityLoadedEvent $event): void
    {
        $this->indexIfNeeded($event);

        /** @var RuleEntity $entity */
        foreach ($event->getEntities() as $entity) {
            if (!$entity->getPayload()) {
                continue;
            }

            $unserialized = unserialize($entity->getPayload());

            $entity->setPayload($unserialized);
        }
    }

    private function indexIfNeeded(EntityLoadedEvent $event): void
    {
        $entities = $event->getEntities()->filter(function (RuleEntity $rule) {
            return $rule->getPayload() === null && !$rule->isInvalid();
        });

        if (!$entities->count()) {
            return;
        }

        $updated = $this->indexer->update($entities->getIds());

        foreach ($updated as $id => $entity) {
            $entities->get($id)->assign($entity);
        }
    }
}
