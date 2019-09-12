<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Test;

use Doctrine\DBAL\Connection;
use Elasticsearch\Client;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityAggregator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntitySearcher;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\CacheTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\FilesystemBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SessionTestBehaviour;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\CriteriaParser;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\ElasticsearchEntityAggregator;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\ElasticsearchEntitySearcher;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Shopware\Elasticsearch\Framework\Indexing\CreateAliasTaskHandler;
use Shopware\Elasticsearch\Framework\Indexing\EntityIndexer;
use Symfony\Component\DependencyInjection\ContainerInterface;

trait ElasticsearchTestTestBehaviour
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;
    use FilesystemBehaviour;
    use CacheTestBehaviour;
    use BasicTestDataBehaviour;
    use SessionTestBehaviour;

    use QueueTestBehaviour;

    /**
     * @before
     */
    public function enableElasticsearch(): void
    {
        $this->getDiContainer()
            ->get(ElasticsearchHelper::class)
            ->setEnabled(true);
    }

    /**
     * @after
     */
    public function disableElasticsearch(): void
    {
        $this->getDiContainer()
            ->get(ElasticsearchHelper::class)
            ->setEnabled(false);
    }

    /**
     * @beforeClass
     */
    public static function start(): void
    {
        $self = new self();
        $self->getContainer()
            ->get(Connection::class)
            ->beginTransaction();
    }

    /**
     * @afterClass
     */
    public static function cleanupElasticsearchIndices(): void
    {
        $self = new self();
        $self->getDiContainer()->get(Client::class)
            ->indices()->delete(['index' => '_all']);

        $self->getContainer()
            ->get(Connection::class)
            ->rollBack();
    }

    public function indexElasticSearch(): void
    {
        $this->getDiContainer()
            ->get(EntityIndexer::class)
            ->index(new \DateTime());

        $this->runWorker();

        $this->getDiContainer()
            ->get(Client::class)
            ->indices()
            ->refresh();

        $this->getDiContainer()
            ->get(CreateAliasTaskHandler::class)
            ->run();

        $client = $this->getDiContainer()->get(Client::class);
        $client->indices()->refresh();
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
            $this->getDiContainer()->get(DefinitionInstanceRegistry::class)
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
            $this->getDiContainer()->get(CriteriaParser::class)
        );
    }

    abstract protected function getDiContainer(): ContainerInterface;
}
