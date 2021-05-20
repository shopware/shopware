<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Command;

use Elasticsearch\Client;
use Shopware\Elasticsearch\Framework\ElasticsearchOutdatedIndexDetector;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ElasticsearchCleanIndicesCommand extends Command
{
    protected static $defaultName = 'es:index:cleanup';

    private ElasticsearchOutdatedIndexDetector $outdatedIndexDetector;

    private Client $client;

    public function __construct(
        Client $client,
        ElasticsearchOutdatedIndexDetector $indexCleaner
    ) {
        parent::__construct();
        $this->outdatedIndexDetector = $indexCleaner;
        $this->client = $client;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Do not ask for confirmation')
            ->setDescription('Admin command to remove old and unused indices');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $indices = $this->outdatedIndexDetector->get();

        if (empty($indices)) {
            $io->writeln('No indices to be deleted.');

            return self::SUCCESS;
        }

        $io->table(['Indices to be deleted:'], array_map(static fn (string $name) => [$name], $indices));

        if (!$input->getOption('force')) {
            if (!$io->confirm(sprintf('Delete these %d indices?', \count($indices)), false)) {
                $io->writeln('Deletion aborted.');

                return self::FAILURE;
            }
        }

        foreach ($indices as $index) {
            $this->client->indices()->delete(['index' => $index]);
        }

        $io->writeln('Indices deleted.');

        return self::SUCCESS;
    }
}
