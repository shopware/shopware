<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\DataAbstractionLayer\Command\ConsoleProgressTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Admin\AdminIndexingBehavior;
use Shopware\Elasticsearch\Admin\AdminSearchRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[AsCommand(
    name: 'es:admin:index',
    description: 'Index the elasticsearch for the admin search',
)]
#[Package('system-settings')]
final class ElasticsearchAdminIndexingCommand extends Command implements EventSubscriberInterface
{
    use ConsoleProgressTrait;

    /**
     * @internal
     */
    public function __construct(private readonly AdminSearchRegistry $registry)
    {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->addOption('no-queue', null, null, 'Do not use the queue for indexing');
        $this->addOption('skip', null, InputArgument::OPTIONAL, 'Comma separated list of entity names to be skipped');
        $this->addOption('only', null, InputArgument::OPTIONAL, 'Comma separated list of entity names to be generated');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new ShopwareStyle($input, $output);

        $skip = \is_string($input->getOption('skip')) ? explode(',', $input->getOption('skip')) : [];
        $only = \is_string($input->getOption('only')) ? explode(',', $input->getOption('only')) : [];

        $this->registry->iterate(
            new AdminIndexingBehavior(
                (bool) $input->getOption('no-queue'),
                $skip,
                $only
            )
        );

        return self::SUCCESS;
    }
}
