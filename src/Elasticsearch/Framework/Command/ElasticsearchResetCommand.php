<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Command;

use Doctrine\DBAL\Connection;
use Elasticsearch\Client;
use Shopware\Elasticsearch\Framework\ElasticsearchOutdatedIndexDetector;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ElasticsearchResetCommand extends Command
{
    protected static $defaultName = 'es:reset';

    private ElasticsearchOutdatedIndexDetector $detector;

    private Client $client;

    private Connection $connection;

    public function __construct(Client $client, ElasticsearchOutdatedIndexDetector $detector, Connection $connection)
    {
        parent::__construct();
        $this->detector = $detector;
        $this->client = $client;
        $this->connection = $connection;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Resets Elasticsearch indexing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $answer = $io->ask('Are you sure you want to reset the Elasticsearch indexing?', 'yes');

        if ($answer !== 'yes') {
            $io->error('Canceled clearing indexing process');

            return self::SUCCESS;
        }

        $indices = $this->detector->getAllUsedIndices();

        foreach ($indices as $index) {
            $this->client->indices()->delete(['index' => $index]);
        }

        $this->connection->executeStatement('TRUNCATE elasticsearch_index_task');
        $this->connection->executeStatement('UPDATE message_queue_stats SET size = 0 WHERE name = "Shopware\Elasticsearch\Framework\Indexing\ElasticsearchIndexingMessage"');
        $this->connection->executeStatement('DELETE FROM enqueue WHERE body LIKE "%ElasticsearchIndexingMessage%"');

        $io->success('Elasticsearch indices deleted and queue cleared');

        return self::SUCCESS;
    }
}
