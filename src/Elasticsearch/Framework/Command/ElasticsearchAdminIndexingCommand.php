<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\DataAbstractionLayer\Command\ConsoleProgressTrait;
use Shopware\Elasticsearch\Admin\AdminSearchRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @package system-settings
 *
 * @internal
 */
final class ElasticsearchAdminIndexingCommand extends Command implements EventSubscriberInterface
{
    use ConsoleProgressTrait;

    public static $defaultDescription = 'Index the elasticsearch for the admin search';

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
        $this->addOption('no-queue', null, null, 'Do not use the queue for indexing');
        $this->addOption('entities', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Entities will be indexed');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new ShopwareStyle($input, $output);

        $this->registry->iterate((bool) $input->getOption('no-queue'), $input->getOption('entities'));

        return self::SUCCESS;
    }
}
