<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Command;

use Shopware\Core\Framework\Console\ShopwareStyle;
use Shopware\Core\Framework\DataAbstractionLayer\Command\ConsoleProgressTrait;
use Shopware\Elasticsearch\Framework\Indexing\EntityIndexer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ElasticsearchIndexingCommand extends Command implements EventSubscriberInterface
{
    use ConsoleProgressTrait;

    protected static $defaultName = 'es:index';

    /**
     * @var EntityIndexer
     */
    private $indexer;

    public function __construct(EntityIndexer $indexer)
    {
        parent::__construct();
        $this->indexer = $indexer;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Reindex all entities to elasticsearch');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io = new ShopwareStyle($input, $output);

        $this->indexer->index(new \DateTime());

        return null;
    }
}
