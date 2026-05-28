<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppStorage;
use Shopware\Core\Framework\Context;
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
     */
    public function __construct(private readonly AppStorage $appStorage)
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

        $filter = $input->getOption('filter');
        $apps = \is_string($filter) && $filter !== ''
            ? $this->appStorage->findAllWithNameOrLabel($filter, $context)
            : $this->appStorage->findAll($context);
        $apps->sort(static fn (AppEntity $a, AppEntity $b): int => $a->getName() <=> $b->getName());

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
