<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:list',
    description: 'Lists all apps',
)]
#[Package('framework')]
class AppListCommand extends Command
{
    /**
     * @internal
     *
     * @param EntityRepository<AppCollection> $appRepository
     */
    public function __construct(private readonly EntityRepository $appRepository)
    {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->addOption('json', null, InputOption::VALUE_NONE, 'Return result as json of app entities')
            ->addOption('filter', 'f', InputOption::VALUE_REQUIRED, 'Filter the app list to a given term');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);
        $context = Context::createCLIContext();

        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));
        $filter = $input->getOption('filter');
        if ($filter) {
            $criteria->addFilter(new MultiFilter(
                MultiFilter::CONNECTION_OR,
                [
                    new ContainsFilter('name', $filter),
                    new ContainsFilter('label', $filter),
                ]
            ));
        }

        $apps = $this->appRepository->search($criteria, $context)->getEntities();

        if ($input->getOption('json')) {
            $output->write(json_encode($apps, \JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $appTable = [];
        $active = 0;

        $io->title('Shopware App Service');

        if ($filter) {
            $io->comment(\sprintf('Filtering for: %s', $filter));
        }

        foreach ($apps as $app) {
            $appTable[] = [
                $app->getName(),
                $app->getLabel() ? mb_strimwidth($app->getLabel(), 0, 40, '...') : '',
                $app->getVersion(),
                $app->getAuthor(),
                $app->isActive() ? 'Yes' : 'No',
            ];

            if ($app->isActive()) {
                ++$active;
            }
        }

        $io->table(
            ['App', 'Label', 'Version', 'Author', 'Active'],
            $appTable
        );

        $io->text(
            \sprintf(
                '%d apps, %d active',
                \count($appTable),
                $active
            )
        );

        return self::SUCCESS;
    }
}
