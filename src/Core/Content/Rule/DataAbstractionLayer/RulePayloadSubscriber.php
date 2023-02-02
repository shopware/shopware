<?php declare(strict_types=1);

namespace Shopware\Core\Content\Rule\DataAbstractionLayer;

use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Content\Rule\RuleEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Container\Container;
use Shopware\Core\Framework\Rule\Container\FilterRule;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\ScriptRule;
use Shopware\Core\Framework\Script\Debugging\ScriptTraces;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('business-ops')]
class RulePayloadSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly RulePayloadUpdater $updater,
        private readonly ScriptTraces $traces,
        private readonly string $cacheDir,
        private readonly bool $debug
    ) {
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
            $payload = $entity->getPayload();
            if ($payload === null || !\is_string($payload)) {
                continue;
            }

            $payload = unserialize($payload);

            $this->enrichConditions([$payload]);

            $entity->setPayload($payload);
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
    }

    /**
     * @param list<Rule> $conditions
     */
    private function enrichConditions(array $conditions): void
    {
        foreach ($conditions as $condition) {
            if ($condition instanceof ScriptRule) {
                $condition->assign([
                    'traces' => $this->traces,
                    'cacheDir' => $this->cacheDir,
                    'debug' => $this->debug,
                ]);

                continue;
            }

            if ($condition instanceof Container || $condition instanceof FilterRule) {
                $this->enrichConditions($condition->getRules());
            }
        }
    }
}
