<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Command;

use Doctrine\DBAL\Connection;
use OpenSearch\Client;
use Shopware\Core\Framework\Increment\Exception\IncrementGatewayNotFoundException;
use Shopware\Core\Framework\Increment\IncrementGatewayRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Admin\AdminElasticsearchHelper;
use Shopware\Elasticsearch\Admin\AdminSearchIndexingMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[AsCommand(
    name: 'es:admin:reset',
    description: 'Reset Admin Elasticsearch indexing',
)]
#[Package('system-settings')]
class ElasticsearchAdminResetCommand extends Command
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Client $client,
        private readonly Connection $connection,
        private readonly IncrementGatewayRegistry $gatewayRegistry,
        private readonly AdminElasticsearchHelper $adminEsHelper
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($this->adminEsHelper->getEnabled() !== true) {
            $io->error('Admin elasticsearch is not enabled');

            return self::FAILURE;
        }

        $answer = $io->ask('Are you sure you want to reset the Admin Elasticsearch indexing?', 'yes');

        if ($answer !== 'yes') {
            $io->error('Canceled clearing indexing process');

            return self::SUCCESS;
        }

        $allIndices = $this->client->indices()->get(['index' => $this->adminEsHelper->getPrefix() . '*']);

        foreach ($allIndices as $index) {
            $this->client->indices()->delete(['index' => $index['settings']['index']['provided_name']]);
        }

        $this->connection->executeStatement('TRUNCATE admin_elasticsearch_index_task');

        try {
            $gateway = $this->gatewayRegistry->get(IncrementGatewayRegistry::MESSAGE_QUEUE_POOL);
            $gateway->reset('message_queue_stats', AdminSearchIndexingMessage::class);
        } catch (IncrementGatewayNotFoundException) {
            // In case message_queue pool is disabled
        }

        $io->success('Admin Elasticsearch indices deleted and queue cleared');

        return self::SUCCESS;
    }
}
