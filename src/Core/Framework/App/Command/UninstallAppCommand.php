<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\AbstractAppLifecycle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
#[AsCommand(
    name: 'app:uninstall',
    description: 'Uninstalls an app',
)]
#[Package('core')]
class UninstallAppCommand extends Command
{
    public function __construct(
        private readonly AbstractAppLifecycle $appLifecycle,
        private readonly EntityRepository $appRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);

        $name = $input->getArgument('name');

        if (!\is_string($name)) {
            throw new \InvalidArgumentException('Argument $name must be an string');
        }

        $context = Context::createDefaultContext();
        $app = $this->getAppByName($name, $context);

        if (!$app) {
            $io->error(sprintf('No app with name "%s" installed.', $name));

            return self::FAILURE;
        }

        $keepUserData = $input->getOption('keep-user-data');

        $this->appLifecycle->delete(
            $app->getName(),
            [
                'id' => $app->getId(),
                'roleId' => $app->getAclRoleId(),
            ],
            $context,
            $keepUserData
        );

        $io->success('App uninstalled successfully.');

        return self::SUCCESS;
    }

    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'The name of the app');
        $this->addOption('keep-user-data', null, InputOption::VALUE_NONE, 'Keep user data of the app');
    }

    private function getAppByName(string $name, Context $context): ?AppEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $name));

        return $this->appRepository->search($criteria, $context)->first();
    }
}
