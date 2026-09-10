<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework;

use OpenSearch\Client;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class ElasticsearchOutdatedIndexDetector
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Client $client,
        private readonly ElasticsearchRegistry $registry,
        private readonly ElasticsearchHelper $helper
    ) {
    }

    /**
     * @return array<string>
     */
    public function get(): ?array
    {
        $allIndices = $this->getAllIndices();

        if ($allIndices === []) {
            return [];
        }

        $indicesToBeDeleted = [];
        foreach ($allIndices as $index) {
            if (\count($index['aliases']) > 0) {
                continue;
            }

            $indicesToBeDeleted[] = $index['settings']['index']['provided_name'];
        }

        return $indicesToBeDeleted;
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
        $indicesToBeDeleted = [];

        foreach ($this->getAllIndices() as $index) {
            if (\count($index['aliases']) > 0) {
                continue;
            }

            $creationDate = (int) ($index['settings']['index']['creation_date'] ?? 0);

            // creation_date is epoch milliseconds. A missing value is treated as "too young to touch" so an index we
            // cannot date is never deleted unattended.
            if ($creationDate === 0 || $creationDate >= $createdBefore->getTimestamp() * 1000) {
                continue;
            }

            $indicesToBeDeleted[] = $index['settings']['index']['provided_name'];
        }

        return $indicesToBeDeleted;
    }

    /**
     * @return array<string>
     */
    public function getAllUsedIndices(): array
    {
        $allIndices = $this->getAllIndices();

        return array_map(static fn (array $index) => $index['settings']['index']['provided_name'], $allIndices);
    }

    /**
     * @return array<string>
     */
    private function getPrefixes(): array
    {
        $definitions = $this->registry->getDefinitions();

        $prefixes = [];

        foreach ($definitions as $definition) {
            $prefixes[] = \sprintf('%s_*', $this->helper->getIndexName($definition->getEntityDefinition()));
        }

        return $prefixes;
    }

    /**
     * @return array{aliases: array<string>, settings: array<mixed>}[]
     */
    private function getAllIndices(): array
    {
        $prefixes = array_chunk($this->getPrefixes(), 5);

        $allIndices = [];

        foreach ($prefixes as $prefix) {
            $indices = $this->client->indices()->get(
                ['index' => implode(',', $prefix)]
            );

            $allIndices = array_merge($allIndices, $indices);
        }

        return $allIndices;
    }
}
