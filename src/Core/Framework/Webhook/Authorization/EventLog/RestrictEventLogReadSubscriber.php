<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Authorization\EventLog;

use Shopware\Core\Framework\Api\Acl\ApiCaller;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\BeforeEntityAggregationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\WebhookCollection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Framework\Webhook\EventLog\WebhookEventLogApiTest
 */
#[Package('framework')]
class RestrictEventLogReadSubscriber implements EventSubscriberInterface, ResetInterface
{
    /**
     * @var array<string, list<string>>
     */
    private array $ownedWebhookIds = [];

    /**
     * @param EntityRepository<WebhookCollection> $webhookRepository
     */
    public function __construct(private readonly EntityRepository $webhookRepository)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EntitySearchedEvent::class => 'restrictSearch',
            BeforeEntityAggregationEvent::class => 'restrictAggregation',
        ];
    }

    public function restrictSearch(EntitySearchedEvent $event): void
    {
        if ($event->getDefinition()->getEntityName() !== WebhookEventLogDefinition::ENTITY_NAME) {
            return;
        }

        $restriction = $this->getRestriction($event->getContext());
        if ($restriction === null) {
            return;
        }

        $event->getCriteria()->addFilter($restriction);
    }

    public function restrictAggregation(BeforeEntityAggregationEvent $event): void
    {
        if ($event->getDefinition()->getEntityName() !== WebhookEventLogDefinition::ENTITY_NAME) {
            return;
        }

        $restriction = $this->getRestriction($event->getContext());
        if ($restriction === null) {
            return;
        }

        $criteria = $event->getCriteria();
        $aggregations = $criteria->getAggregations();
        $criteria->resetAggregations();
        foreach ($aggregations as $aggregation) {
            $criteria->addAggregation(new FilterAggregation('Sanitized ' . $aggregation->getName(), $aggregation, [$restriction]));
        }
    }

    public function reset(): void
    {
        $this->ownedWebhookIds = [];
    }

    private function getRestriction(Context $context): ?Filter
    {
        $source = $context->getSource();
        if ($context->getScope() === Context::SYSTEM_SCOPE || !$source instanceof AdminApiSource || $source->isAdmin()) {
            return null;
        }

        $caller = ApiCaller::of($source);
        if ($caller === null) {
            return new EqualsAnyFilter('webhookId', []);
        }

        $this->ownedWebhookIds[$caller->id] ??= $this->getWebhookIdsOwnedBy($caller);

        return new EqualsAnyFilter('webhookId', $this->ownedWebhookIds[$caller->id]);
    }

    /**
     * @return list<string>
     */
    private function getWebhookIdsOwnedBy(ApiCaller $caller): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(match ($caller->type) {
            ApiCaller::USER => new EqualsFilter('ownerUserId', $caller->id),
            ApiCaller::INTEGRATION => new OrFilter([
                new EqualsFilter('ownerIntegrationId', $caller->id),
                new EqualsFilter('app.integrationId', $caller->id),
            ]),
        });

        /** @var list<string> $ids */
        $ids = $this->webhookRepository->searchIds($criteria, Context::createDefaultContext())->getIds();

        return $ids;
    }
}
