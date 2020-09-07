<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Persister;

use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Webhook\WebhookCollection;
use Shopware\Core\Framework\Webhook\WebhookEntity;

class WebhookPersister
{
    /**
     * @var EntityRepositoryInterface
     */
    private $webhookRepository;

    public function __construct(EntityRepositoryInterface $webhookRepository)
    {
        $this->webhookRepository = $webhookRepository;
    }

    public function updateWebhooks(Manifest $manifest, string $appId, Context $context): void
    {
        $existingWebhooks = $this->getExistingWebhooks($appId, $context);

        $webhooks = $manifest->getWebhooks() ? $manifest->getWebhooks()->getWebhooks() : [];
        $upserts = [];

        foreach ($webhooks as $webhook) {
            $payload = $webhook->toArray();
            $payload['appId'] = $appId;
            $payload['eventName'] = $webhook->getEvent();

            /** @var WebhookEntity|null $existing */
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

    private function deleteOldWebhooks(WebhookCollection $toBeRemoved, Context $context): void
    {
        /** @var array<string> $ids */
        $ids = $toBeRemoved->getIds();

        if (!empty($ids)) {
            $ids = array_map(static function (string $id): array {
                return ['id' => $id];
            }, array_values($ids));

            $this->webhookRepository->delete($ids, $context);
        }
    }

    private function getExistingWebhooks(string $appId, Context $context): WebhookCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));

        /** @var WebhookCollection $webhooks */
        $webhooks = $this->webhookRepository->search($criteria, $context)->getEntities();

        return $webhooks;
    }
}
