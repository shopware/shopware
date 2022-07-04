<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Context;
use Shopware\Elasticsearch\Admin\AdminSearcher;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ElasticsearchAdminTestCommand extends Command
{
    protected static $defaultName = 'es:admin:test';

    private AdminSearcher $searcher;

    /**
     * @internal
     */
    public function __construct(AdminSearcher $searcher)
    {
        parent::__construct();
        $this->searcher = $searcher;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->setDescription('Allows you to test the admin search index')
            ->addArgument('term', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new ShopwareStyle($input, $output);

        $term = $input->getArgument('term');

        $result = $this->searcher->search($term, Context::createDefaultContext());

        $rows = [];
        foreach ($result as $data) {
            $rows[] = [$data['index'], $data['indexer'], $data['total']];
        }

        $this->io->table(['Index', 'Indexer', 'total'], $rows);

        return self::SUCCESS;
    }
}
