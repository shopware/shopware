<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Admin;

use OpenSearch\Client;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
class AdminElasticsearchOutdatedIndexDetector
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Client $client,
        private readonly AdminElasticsearchHelper $adminEsHelper
    ) {
    }

    /**
     * @return array<string>
     */
    public function get(): array
    {
        return $this->collect(null);
    }

    /**
     * Restricts the result to indices that were created before the given point in time. An index that is currently
     * being built carries no alias either, so an age threshold is what separates a leftover from a running indexing
     * run for unattended callers.
     *
     * @return array<string>
     */
    public function getOutdated(\DateTimeInterface $createdBefore): array
    {
        return $this->collect($createdBefore);
    }

    /**
     * @return array<string>
     */
    private function collect(?\DateTimeInterface $createdBefore): array
    {
        $allIndices = $this->client->indices()->get(['index' => $this->adminEsHelper->getPrefix() . '*']);

        $indicesToBeDeleted = [];
        foreach ($allIndices as $index) {
            if (\count($index['aliases']) > 0) {
                continue;
            }

            if ($createdBefore !== null && !$this->isCreatedBefore($index, $createdBefore)) {
                continue;
            }

            $indicesToBeDeleted[] = $index['settings']['index']['provided_name'];
        }

        return $indicesToBeDeleted;
    }

    /**
     * @param array{aliases: array<string>, settings: array<mixed>} $index
     */
    private function isCreatedBefore(array $index, \DateTimeInterface $createdBefore): bool
    {
        $creationDate = (int) ($index['settings']['index']['creation_date'] ?? 0);

        // creation_date is epoch milliseconds. A missing value is treated as "too young to touch" so an index we
        // cannot date is never deleted unattended.
        if ($creationDate === 0) {
            return false;
        }

        return $creationDate < $createdBefore->getTimestamp() * 1000;
    }
}
