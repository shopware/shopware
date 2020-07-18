<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework;

use Elasticsearch\Client;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\Language\LanguageCollection;

class ElasticsearchOutdatedIndexDetector
{
    /**
     * @var Client
     */
    private $client;

    /**
     * @var ElasticsearchRegistry
     */
    private $registry;

    /**
     * @var EntityRepositoryInterface
     */
    private $languageRepository;

    /**
     * @var ElasticsearchHelper
     */
    private $helper;

    public function __construct(
        Client $client,
        ElasticsearchRegistry $esRegistry,
        EntityRepositoryInterface $languageRepository,
        ElasticsearchHelper $helper
    ) {
        $this->client = $client;
        $this->registry = $esRegistry;
        $this->languageRepository = $languageRepository;
        $this->helper = $helper;
    }

    /**
     * @return string[]
     */
    public function get(): ?array
    {
        $allIndices = $this->client->indices()->get(
            ['index' => \implode(',', $this->getPrefixes())]
        );

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

    private function getLanguages(): EntityCollection
    {
        return (Context::createDefaultContext())->disableCache(
            function (Context $uncached) {
                return $this
                    ->languageRepository
                    ->search(new Criteria(), $uncached)
                    ->getEntities();
            }
        );
    }

    /**
     * @return string[]
     */
    private function getPrefixes(): array
    {
        $definitions = $this->registry->getDefinitions();

        /** @var LanguageCollection $languages */
        $languages = $this->getLanguages();

        $prefixes = [];
        foreach ($languages as $language) {
            foreach ($definitions as $definition) {
                $prefixes[] = \sprintf('%s_*', $this->helper->getIndexName($definition->getEntityDefinition(), $language->getId()));
            }
        }

        return $prefixes;
    }
}
