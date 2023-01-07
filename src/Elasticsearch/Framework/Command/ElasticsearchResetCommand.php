<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Command;

use Doctrine\DBAL\Connection;
use OpenSearch\Client;
use Shopware\Core\Framework\Increment\Exception\IncrementGatewayNotFoundException;
use Shopware\Core\Framework\Increment\IncrementGatewayRegistry;
use Shopware\Elasticsearch\Framework\ElasticsearchOutdatedIndexDetector;
use Shopware\Elasticsearch\Framework\Indexing\ElasticsearchIndexingMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @package core
 */
#[AsCommand(
    name: 'es:reset',
    description: 'Reset the elasticsearch index',
)]
class ElasticsearchResetCommand extends Command
{
    private ElasticsearchOutdatedIndexDetector $detector;

    private Client $client;

    private Connection $connection;

    private IncrementGatewayRegistry $gatewayRegistry;

    /**
     * @internal
     */
    public function __construct(Client $client, ElasticsearchOutdatedIndexDetector $detector, Connection $connection, IncrementGatewayRegistry $gatewayRegistry)
    {
        parent::__construct();
        $this->detector = $detector;
        $this->client = $client;
        $this->connection = $connection;
        $this->gatewayRegistry = $gatewayRegistry;
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

        try {
            $gateway = $this->gatewayRegistry->get(IncrementGatewayRegistry::MESSAGE_QUEUE_POOL);
            $gateway->reset('message_queue_stats', ElasticsearchIndexingMessage::class);
        } catch (IncrementGatewayNotFoundException $exception) {
            // In case message_queue pool is disabled
        }

        $this->connection->executeStatement('DELETE FROM enqueue WHERE body LIKE "%ElasticsearchIndexingMessage%"');

        $io->success('Elasticsearch indices deleted and queue cleared');

        return self::SUCCESS;
    }
}
