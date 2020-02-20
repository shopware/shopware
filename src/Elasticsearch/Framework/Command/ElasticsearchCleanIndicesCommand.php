<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Command;

use Elasticsearch\Client;
use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Elasticsearch\Framework\ElasticsearchOutdatedIndexDetector;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ElasticsearchCleanIndicesCommand extends Command
{
    protected static $defaultName = 'es:index:cleanup';

    /**
     * @var ShopwareStyle
     */
    private $io;

    /**
     * @var ElasticsearchOutdatedIndexDetector
     */
    private $outdatedIndexDetector;

    /**
     * @var Client
     */
    private $client;

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
        $this->io = new ShopwareStyle($input, $output);

        $indices = $this->outdatedIndexDetector->get();

        if (empty($indices)) {
            $this->io->writeln('No indices to be deleted.');

            return 0;
        }

        $this->io->table(['Indices to be deleted:'], array_map(static function (string $name) {
            return [$name];
        }, $indices));

        if (!$input->getOption('force')) {
            if (!$this->io->confirm(sprintf('Delete these %d indices?', count($indices)), false)) {
                $this->io->writeln('Deletion aborted.');

                return 1;
            }
        }

        $this->client->indices()->delete(['index' => implode(',', $indices)]);

        $this->io->writeln('Indices deleted.');

        return 0;
    }
}
