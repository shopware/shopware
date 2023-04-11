<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\SalesChannel\Command;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal should be used over the CLI only
 */
#[AsCommand(
    name: 'sales-channel:maintenance:enable',
    description: 'Enable maintenance mode for a sales channel',
)]
#[Package('core')]
class SalesChannelMaintenanceEnableCommand extends Command
{
    /**
     * @var bool
     */
    protected $setMaintenanceMode = true;

    public function __construct(
        private readonly EntityRepository $salesChannelRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'ids',
            InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
            'Which Sales Channels do you want to update maintenance mode for? (Optional when --all flag is used)',
            []
        )->addOption(
            'all',
            'a',
            InputOption::VALUE_NONE,
            'Set maintenance mode for all sales channels'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $context = Context::createDefaultContext();
        $criteria = new Criteria();

        if (!$input->getOption('all')) {
            $ids = $input->getArgument('ids');
            if ($ids === []) {
                $output->write('No sales channels were updated. Provide id(s) or run with --all option.');

                return self::SUCCESS;
            }

            $criteria->setIds($ids);
        }

        /** @var array<string> $salesChannelIds */
        $salesChannelIds = $this->salesChannelRepository->searchIds($criteria, $context)->getIds();

        if (empty($salesChannelIds)) {
            $output->write(sprintf('No sales channels were updated'));

            return self::SUCCESS;
        }

        $update = array_map(fn (string $id) => [
            'id' => $id,
            'maintenance' => $this->setMaintenanceMode,
        ], $salesChannelIds);

        $this->salesChannelRepository->update($update, $context);

        $output->write(sprintf('Updated maintenance mode for %d sales channel(s)', \count($salesChannelIds)));

        return self::SUCCESS;
    }
}
