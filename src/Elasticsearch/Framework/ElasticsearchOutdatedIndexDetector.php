<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework;

use Doctrine\DBAL\Connection;
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

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(
        Client $client,
        ElasticsearchRegistry $esRegistry,
        EntityRepositoryInterface $languageRepository,
        ElasticsearchHelper $helper,
        Connection $connection
    ) {
        $this->client = $client;
        $this->registry = $esRegistry;
        $this->languageRepository = $languageRepository;
        $this->helper = $helper;
        $this->connection = $connection;
    }

    /**
     * @return string[]
     */
    public function get(): ?array
    {
        $exportingIndices = array_map(
            static function (array $col) {
                return $col['index'] ?? '';
            },
            $this->connection->fetchAll('SELECT `index` FROM elasticsearch_index_task')
        );

        $allIndices = $this->client->indices()->get(
            ['index' => implode(',', $this->getPrefixes())]
        );

        if (empty($allIndices)) {
            return [];
        }

        $indicesToBeDeleted = [];
        foreach ($allIndices as $index) {
            $name = $index['settings']['index']['provided_name'];

            if (count($index['aliases']) > 0 || in_array($name, $exportingIndices, true)) {
                continue;
            }

            $indicesToBeDeleted[] = $name;
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
                $prefixes[] = sprintf('%s_*', $this->helper->getIndexName($definition->getEntityDefinition(), $language->getId()));
            }
        }

        return $prefixes;
    }
}
