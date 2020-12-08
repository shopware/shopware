<?php declare(strict_types=1);

namespace Shopware\Core\Content\Rule\DataAbstractionLayer;

use Shopware\Core\Content\Rule\RuleDefinition;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Content\Rule\RuleEvents;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class RulePayloadSubscriber implements EventSubscriberInterface
{
    /**
     * @var CacheClearer
     */
    private $cacheClearer;

    /**
     * @var RulePayloadUpdater
     */
    private $updater;

    public function __construct(RulePayloadUpdater $updater, CacheClearer $cacheClearer)
    {
        $this->updater = $updater;
        $this->cacheClearer = $cacheClearer;
    }

    public static function getSubscribedEvents(): array
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
            if (!$entity->getPayload() || !\is_string($entity->getPayload())) {
                continue;
            }

            $unserialized = unserialize($entity->getPayload());

            $entity->setPayload($unserialized);
        }
    }

    private function indexIfNeeded(EntityLoadedEvent $event): void
    {
        $rules = [];

        /** @var RuleEntity $rule */
        foreach ($event->getEntities() as $rule) {
            if ($rule->getPayload() === null && !$rule->isInvalid()) {
                $rules[$rule->getId()] = $rule;
            }
        }

        if (!\count($rules)) {
            return;
        }

        $updated = $this->updater->update(array_keys($rules));

        foreach ($updated as $id => $entity) {
            $rules[$id]->assign($entity);
        }

        $this->cacheClearer->invalidateIds(array_keys($updated), RuleDefinition::ENTITY_NAME);
    }
}
