<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Persister;

use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\WebhookCollection;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class WebhookPersister
{
    /**
     * @param EntityRepository<WebhookCollection> $webhookRepository
     */
    public function __construct(private readonly EntityRepository $webhookRepository)
    {
    }

    public function updateWebhooks(Manifest $manifest, string $appId, string $defaultLocale, Context $context): void
    {
        $existingWebhooks = $this->getExistingWebhooks($appId, $context);

        $webhooks = $manifest->getWebhooks() ? $manifest->getWebhooks()->getWebhooks() : [];
        $upserts = [];

        foreach ($webhooks as $webhook) {
            $payload = $webhook->toArray($defaultLocale);
            $payload['appId'] = $appId;
            $payload['eventName'] = $webhook->getEvent();

            $existing = $existingWebhooks->filterByProperty('name', $webhook->getName())->first();
            if ($existing) {
                $payload['id'] = $existing->getId();
                $existingWebhooks->remove($existing->getId());
            }

            $upserts[] = $payload;
        }

        if (!empty($upserts)) {
            $this->webhookRepository->upsert($upserts, $context);
        }

        $this->deleteOldWebhooks($existingWebhooks, $context);
    }

    /**
     * @param array<array{name: string}> $webhooks
     */
    public function updateWebhooksFromArray(array $webhooks, string $appId, Context $context): void
    {
        $existingWebhooks = $this->getExistingWebhooks($appId, $context);
        $upserts = [];

        foreach ($webhooks as $webhook) {
            $existing = $existingWebhooks->filterByProperty('name', $webhook['name'])->first();

            if ($existing) {
                $webhook['id'] = $existing->getId();
                $existingWebhooks->remove($existing->getId());
            }

            $upserts[] = $webhook;
        }

        if (!empty($upserts)) {
            $this->webhookRepository->upsert($upserts, $context);
        }

        $this->deleteOldWebhooks($existingWebhooks, $context);
    }

    private function deleteOldWebhooks(WebhookCollection $toBeRemoved, Context $context): void
    {
        $ids = $toBeRemoved->getIds();

        if (empty($ids)) {
            return;
        }

        $ids = array_map(static fn (string $id): array => ['id' => $id], array_values($ids));

        $this->webhookRepository->delete($ids, $context);
    }

    private function getExistingWebhooks(string $appId, Context $context): WebhookCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));

        return $this->webhookRepository->search($criteria, $context)->getEntities();
    }
}
