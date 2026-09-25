<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Command;

use OpenSearch\Client;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Admin\AdminElasticsearchHelper;
use Shopware\Elasticsearch\Admin\AdminElasticsearchOutdatedIndexDetector;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[Package('inventory')]
#[AsCommand(
    name: 'es:admin:index:cleanup',
    description: 'Clean outdated admin indices',
)]
class ElasticsearchAdminCleanIndicesCommand extends Command
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Client $client,
        private readonly AdminElasticsearchOutdatedIndexDetector $outdatedIndexDetector,
        private readonly AdminElasticsearchHelper $adminEsHelper
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($this->adminEsHelper->isEnabled() !== true) {
            $io->error('Admin elasticsearch is not enabled');

            return self::FAILURE;
        }

        $indices = $this->outdatedIndexDetector->get();

        if ($indices === []) {
            $io->writeln('No indices to be deleted.');

            return self::SUCCESS;
        }

        $io->table(['Indices to be deleted:'], array_map(static fn (string $name) => [$name], $indices));

        $confirm = $io->confirm(\sprintf('Delete these %d indices?', \count($indices)));

        if (!$confirm) {
            $io->caution('Deletion aborted.');

            return self::SUCCESS;
        }

        foreach ($indices as $index) {
            $this->client->indices()->delete(['index' => $index]);
        }

        $io->writeln('Indices deleted.');

        return self::SUCCESS;
    }
}
