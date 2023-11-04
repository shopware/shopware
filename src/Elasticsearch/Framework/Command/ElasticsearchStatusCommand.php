<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Command;

use Doctrine\DBAL\Connection;
use OpenSearch\Client;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Command\ConsoleProgressTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'es:status',
    description: 'Show the status of the elasticsearch index',
)]
#[Package('core')]
class ElasticsearchStatusCommand extends Command
{
    use ConsoleProgressTrait;

    /**
     * @internal
     */
    public function __construct(
        private readonly Client $client,
        private readonly Connection $connection
    ) {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->client->ping()) {
            throw new \RuntimeException('Elasticsearch server is not accessible');
        }

        $table = new Table($output);
        $table->setHeaders(['Name', 'Status']);
        $health = $this->client->cluster()->health();

        $table->addRow(['Cluster Status', $health['status']]);
        $table->addRow(['Available Nodes', $health['number_of_nodes']]);

        $indexTask = $this->connection->fetchAssociative('SELECT * FROM elasticsearch_index_task WHERE entity = "product" LIMIT 1');
        $totalProducts = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM product WHERE version_id = :liveVersionId AND child_count = 0 OR parent_id IS NOT NULL', ['liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)]);

        // No entry in key
        if ($indexTask === false) {
            $table->addRow(['Indexing', 'completed']);
            $table->render();
            $output->writeln('');

            return self::SUCCESS;
        }

        if ((int) $indexTask['doc_count'] > 0) {
            $table->addRow(['Indexing', 'in progress']);

            $table->render();
            $output->writeln('');

            $progressBar = new ProgressBar($output, $totalProducts);
            $progressBar->advance($totalProducts - $indexTask['doc_count']);
            $output->writeln('');
        } else {
            $table->addRow(['Indexing', 'completed']);
            $table->render();
            $output->writeln('');
        }

        /** @var list<string> $usedIndices */
        $usedIndices = array_keys($this->client->indices()->getAlias(['name' => $indexTask['alias']]));

        $indexName = $indexTask['index'];
        \assert(\is_string($indexName));
        if (!\in_array($indexName, $usedIndices, true)) {
            $io = new SymfonyStyle($input, $output);
            $io->warning(sprintf('Alias will swap at the end of the indexing process from %s to %s', $usedIndices[0], $indexName));
        }

        return self::SUCCESS;
    }
}
