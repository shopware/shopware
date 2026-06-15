<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Test;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use OpenSearch\Client;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use Shopware\Core\Defaults;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityAggregator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntitySearcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Elasticsearch\Framework\Command\ElasticsearchIndexingCommand;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\AbstractElasticsearchAggregationHydrator;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\AbstractElasticsearchSearchHydrator;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\CriteriaParser;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\ElasticsearchEntityAggregator;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\ElasticsearchEntitySearcher;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\DependencyInjection\ContainerInterface;

#[Package('framework')]
trait ElasticsearchTestTestBehaviour
{
    #[Before]
    public function enableElasticsearch(): void
    {
        $this->getDiContainer()
            ->get(ElasticsearchHelper::class)
            ->setEnabled(true);
    }

    #[After]
    public function disableElasticsearch(): void
    {
        $this->getDiContainer()
            ->get(ElasticsearchHelper::class)
            ->setEnabled(false);
    }

    public function indexElasticSearch(): void
    {
        $this->getDiContainer()
            ->get(ElasticsearchIndexingCommand::class)
            ->run(new ArrayInput([]), new NullOutput());

        $this->runWorker();

        $this->refreshIndex();
    }

    public function refreshIndex(): void
    {
        $this->getDiContainer()->get(Client::class)
            ->indices()
            ->refresh(['index' => '*']);
    }

    protected function createEntityAggregator(): ElasticsearchEntityAggregator
    {
        $decorated = $this->createMock(EntityAggregator::class);

        $decorated
            ->expects(static::never())
            ->method('aggregate');

        return new ElasticsearchEntityAggregator(
            $this->getDiContainer()->get(ElasticsearchHelper::class),
            $this->getDiContainer()->get(Client::class),
            $decorated,
            $this->getDiContainer()->get(AbstractElasticsearchAggregationHydrator::class),
            $this->getDiContainer()->get('event_dispatcher'),
            '5s',
            'dfs_query_then_fetch'
        );
    }

    protected function createEntitySearcher(): ElasticsearchEntitySearcher
    {
        $decorated = $this->createMock(EntitySearcher::class);

        $decorated
            ->expects(static::never())
            ->method('search');

        return new ElasticsearchEntitySearcher(
            $this->getDiContainer()->get(Client::class),
            $decorated,
            $this->getDiContainer()->get(ElasticsearchHelper::class),
            $this->getDiContainer()->get(CriteriaParser::class),
            $this->getDiContainer()->get(AbstractElasticsearchSearchHydrator::class),
            $this->getDiContainer()->get('event_dispatcher'),
            '5s',
            'dfs_query_then_fetch'
        );
    }

    abstract protected function getDiContainer(): ContainerInterface;

    abstract protected function runWorker(): void;

    /**
     * @param list<string> $enabledFields
     */
    protected function setSearchConfiguration(bool $andLogic = true, array $enabledFields = ['name']): void
    {
        $connection = $this->getDiContainer()->get(Connection::class);

        $connection->executeStatement(
            'UPDATE product_search_config SET and_logic = ? WHERE language_id = ?',
            [(int) $andLogic, Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]
        );

        $configId = $connection->fetchOne(
            'SELECT id FROM product_search_config WHERE language_id = ?',
            [Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]
        );

        $connection->executeStatement(
            'DELETE FROM product_search_config_field WHERE product_search_config_id = ? AND field LIKE "customFields%"',
            [$configId]
        );

        $connection->executeStatement(
            'UPDATE product_search_config_field SET searchable = 0, tokenize = 0 WHERE product_search_config_id = ?',
            [$configId]
        );

        $connection->executeStatement(
            'UPDATE product_search_config_field SET searchable = 1, tokenize = 1 WHERE product_search_config_id = :configId AND field IN (:fields)',
            ['configId' => $configId, 'fields' => $enabledFields],
            ['fields' => ArrayParameterType::STRING]
        );

        foreach ($enabledFields as $enabledField) {
            if (str_contains($enabledField, 'customFields')) {
                $connection->insert(
                    'product_search_config_field',
                    [
                        'id' => Uuid::randomBytes(),
                        'product_search_config_id' => $configId,
                        'field' => $enabledField,
                        'searchable' => 1,
                        'tokenize' => 0,
                        'ranking' => 0,
                        'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    ]
                );
            }
        }
    }

    /**
     * @param array<string, int> $scores
     */
    protected function setSearchScores(array $scores): void
    {
        $connection = $this->getDiContainer()->get(Connection::class);

        $connection->executeStatement(
            'UPDATE product_search_config_field SET ranking = 0 WHERE product_search_config_id = (SELECT id FROM product_search_config WHERE language_id = ?)',
            [Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]
        );

        foreach ($scores as $field => $ranking) {
            $connection->executeStatement(
                'UPDATE product_search_config_field SET ranking = ? WHERE product_search_config_id = (SELECT id FROM product_search_config WHERE language_id = ?) AND field = ?',
                [$ranking, Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM), $field]
            );
        }
    }

    protected function clearElasticsearch(): void
    {
        $c = $this->getDiContainer();

        $client = $c->get(Client::class);

        $indices = $client->indices()->get(['index' => EnvironmentHelper::getVariable('SHOPWARE_ES_INDEX_PREFIX') . '*']);

        foreach ($indices as $name => $index) {
            $client->indices()->delete(['index' => $name]);
        }

        $connection = $c->get(Connection::class);
        $connection->executeStatement('DELETE FROM elasticsearch_index_task');
    }
}
