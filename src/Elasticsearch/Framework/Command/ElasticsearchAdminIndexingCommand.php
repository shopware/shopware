<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\DataAbstractionLayer\Command\ConsoleProgressTrait;
use Shopware\Elasticsearch\Admin\AdminSearchRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ElasticsearchAdminIndexingCommand extends Command implements EventSubscriberInterface
{
    use ConsoleProgressTrait;

    protected static $defaultName = 'es:admin:index';

    private AdminSearchRegistry $registry;

    /**
     * @internal
     */
    public function __construct(AdminSearchRegistry $registry)
    {
        parent::__construct();
        $this->registry = $registry;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->setDescription('Index the elasticsearch for the admin search');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new ShopwareStyle($input, $output);

        $this->registry->iterate();

        return self::SUCCESS;
    }
}
