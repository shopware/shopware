<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\SalesChannel\Command;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal should be used over the CLI only
 */
#[Package('discovery')]
#[AsCommand(
    name: 'sales-channel:maintenance:enable',
    description: 'Enable maintenance mode for a sales channel',
)]
class SalesChannelMaintenanceEnableCommand extends Command
{
    protected bool $setMaintenanceMode = true;

    /**
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     */
    public function __construct(
        private readonly EntityRepository $salesChannelRepository,
    ) {
        parent::__construct();
    }

    /**
     * @param list<string> $ids
     */
    public function __invoke(
        OutputInterface $output,
        #[Argument(description: 'Which Sales Channels do you want to update maintenance mode for? (Optional when --all flag is used)')]
        array $ids = [],
        #[Option(description: 'Set maintenance mode for all sales channels', shortcut: 'a')]
        bool $all = false,
    ): int {
        $context = Context::createCLIContext();
        $criteria = new Criteria();

        if ($all === false) {
            if ($ids === []) {
                $output->write('No sales channels were updated. Provide id(s) or run with --all option.');

                return self::SUCCESS;
            }

            $criteria->setIds($ids);
        }

        $salesChannels = $this->salesChannelRepository->searchIds($criteria, $context)->getPrimaryKeyData();
        if ($salesChannels === []) {
            $output->write('No sales channels were updated');

            return self::SUCCESS;
        }

        foreach ($salesChannels as &$salesChannel) {
            $salesChannel['maintenance'] = $this->setMaintenanceMode;
        }
        unset($salesChannel);

        $this->salesChannelRepository->update($salesChannels, $context);

        $output->write(\sprintf('Updated maintenance mode for %d sales channel(s)', \count($salesChannels)));

        return self::SUCCESS;
    }
}
