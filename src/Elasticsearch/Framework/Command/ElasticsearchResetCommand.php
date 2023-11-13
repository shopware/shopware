<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Command;

use Doctrine\DBAL\Connection;
use OpenSearch\Client;
use Shopware\Core\Framework\Increment\Exception\IncrementGatewayNotFoundException;
use Shopware\Core\Framework\Increment\IncrementGatewayRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Framework\ElasticsearchOutdatedIndexDetector;
use Shopware\Elasticsearch\Framework\Indexing\ElasticsearchIndexingMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'es:reset',
    description: 'Reset the elasticsearch index',
)]
#[Package('core')]
class ElasticsearchResetCommand extends Command
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Client $client,
        private readonly ElasticsearchOutdatedIndexDetector $detector,
        private readonly Connection $connection,
        private readonly IncrementGatewayRegistry $gatewayRegistry
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
        } catch (IncrementGatewayNotFoundException) {
            // In case message_queue pool is disabled
        }

        $this->connection->executeStatement('DELETE FROM `messenger_messages` WHERE `headers` LIKE "%ElasticsearchIndexingMessage%"');

        $io->success('Elasticsearch indices deleted and queue cleared');

        return self::SUCCESS;
    }
}
