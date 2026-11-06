<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Indexing;

use Doctrine\DBAL\Connection;
use OpenSearch\Client;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Elasticsearch\Admin\AdminElasticsearchHelper;
use Shopware\Elasticsearch\Admin\AdminElasticsearchOutdatedIndexDetector;
use Shopware\Elasticsearch\Framework\ElasticsearchOutdatedIndexDetector;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Deletes indices that no longer serve an alias. "No alias" alone is not enough to call an index outdated: a full
 * reindex only moves the alias once it finished, so its target looks identical to a leftover for the whole run. This
 * handler therefore deletes an index only when it is old enough and is not the write target of a recorded indexing
 * task.
 *
 * @internal
 */
#[Package('framework')]
#[AsMessageHandler(handles: CleanupIndicesTask::class)]
final class CleanupIndicesTaskHandler extends ScheduledTaskHandler
{
    /**
     * @param EntityRepository<ScheduledTaskCollection> $scheduledTaskRepository
     */
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        private readonly Client $client,
        private readonly Client $adminClient,
        private readonly Connection $connection,
        private readonly ElasticsearchOutdatedIndexDetector $detector,
        private readonly AdminElasticsearchOutdatedIndexDetector $adminDetector,
        private readonly AdminElasticsearchHelper $adminEsHelper,
        private readonly ClockInterface $clock,
        private readonly bool $elasticsearchEnabled,
        private readonly int $minimumAge,
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        $createdBefore = $this->clock->now()->sub(new \DateInterval(\sprintf('PT%dS', $this->minimumAge)));

        if ($this->elasticsearchEnabled) {
            $this->delete(
                $this->client,
                $this->detector->getOutdated($createdBefore),
                $this->connection->fetchFirstColumn('SELECT `index` FROM elasticsearch_index_task')
            );
        }

        if ($this->adminEsHelper->isEnabled()) {
            $this->delete(
                $this->adminClient,
                $this->adminDetector->getOutdated($createdBefore),
                $this->connection->fetchFirstColumn('SELECT `index` FROM admin_elasticsearch_index_task')
            );
        }
    }

    /**
     * @param array<string> $outdated
     * @param array<mixed> $inFlight indices a recorded indexing task still writes to
     */
    private function delete(Client $client, array $outdated, array $inFlight): void
    {
        foreach (array_diff($outdated, $inFlight) as $index) {
            $client->indices()->delete(['index' => $index]);

            $this->exceptionLogger->info(\sprintf('Deleted outdated elasticsearch index "%s".', $index));
        }
    }
}
