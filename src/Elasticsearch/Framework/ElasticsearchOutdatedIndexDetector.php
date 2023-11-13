<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework;

use OpenSearch\Client;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class ElasticsearchOutdatedIndexDetector
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Client $client,
        private readonly ElasticsearchRegistry $registry,
        private readonly ElasticsearchHelper $helper,
        private readonly ElasticsearchLanguageProvider $languageProvider
    ) {
    }

    /**
     * @return array<string>
     */
    public function get(): ?array
    {
        $allIndices = $this->getAllIndices();

        if (empty($allIndices)) {
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
     * @return array<string>
     */
    public function getAllUsedIndices(): array
    {
        $allIndices = $this->getAllIndices();

        return array_map(fn (array $index) => $index['settings']['index']['provided_name'], $allIndices);
    }

    /**
     * @return array<string>
     */
    private function getPrefixes(): array
    {
        $definitions = $this->registry->getDefinitions();

        $prefixes = [];

        if ($this->helper->enabledMultilingualIndex()) {
            foreach ($definitions as $definition) {
                $prefixes[] = sprintf('%s_*', $this->helper->getIndexName($definition->getEntityDefinition()));
            }

            return $prefixes;
        }

        $languages = $this->languageProvider->getLanguages(Context::createDefaultContext());

        foreach ($languages as $language) {
            foreach ($definitions as $definition) {
                $prefixes[] = sprintf('%s_*', $this->helper->getIndexName($definition->getEntityDefinition(), $language->getId()));
            }
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
