<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Command;

use Shopware\Core\Framework\Console\ShopwareStyle;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerRegistryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class RefreshIndexCommand extends Command implements EventSubscriberInterface
{
    use ConsoleProgressTrait;

    /**
     * @var IndexerRegistryInterface
     */
    private $indexer;

    public function __construct(IndexerRegistryInterface $indexer)
    {
        parent::__construct('dal:refresh:index');
        $this->indexer = $indexer;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setName('dal:refresh:index')
            ->setDescription('Refreshes the shop indices');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io = new ShopwareStyle($input, $output);

        $this->indexer->index(new \DateTime());

        return null;
    }
}
