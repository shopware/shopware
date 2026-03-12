<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\Lifecycle\AppSecretRotationService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal only for use by the app-system
 */
#[AsCommand(
    name: 'app:secret:rotate',
    description: 'Rotate the shared app secret and integration credentials for one or all apps.',
)]
#[Package('framework')]
class RotateAppSecretCommand extends Command
{
    /**
     * @param EntityRepository<AppCollection> $appRepository
     */
    public function __construct(
        private readonly EntityRepository $appRepository,
        private readonly AppSecretRotationService $rotationService,
        private readonly ActiveAppsLoader $activeAppsLoader
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::OPTIONAL, 'The name of the app (if not provided, rotates all active apps)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);
        $context = Context::createCLIContext();

        $name = $input->getArgument('name');

        if ($name !== null && !\is_string($name)) {
            throw new \InvalidArgumentException('Argument $name must be a string');
        }

        $appNames = $this->getAppNamesToRotate($name);
        if ($appNames === []) {
            $io->note('No active apps found.');

            return self::SUCCESS;
        }

        $apps = $this->fetchApps($appNames, $context);

        if ($apps->count() === 0) {
            if ($name !== null) {
                $io->error(\sprintf('No app found for "%s".', $name));

                return self::FAILURE;
            }

            $io->note('No apps found.');

            return self::SUCCESS;
        }

        $failedApps = [];

        foreach ($apps as $app) {
            try {
                $this->rotationService->rotateNow($app->getId(), $context, AppSecretRotationService::TRIGGER_CLI);
                $io->success(\sprintf('App %s secrets rotated successfully.', $app->getName()));
            } catch (\Throwable $exception) {
                $io->error(\sprintf('App %s secret rotation failed due: %s', $app->getName(), $exception->getMessage()));
                $failedApps[] = $app->getName();
            }
        }

        if (!empty($failedApps)) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function getAppNamesToRotate(?string $name): array
    {
        if ($name !== null) {
            return [$name];
        }

        $activeApps = $this->activeAppsLoader->getActiveApps();

        return array_values(array_map(fn (array $app) => $app['name'], $activeApps));
    }

    /**
     * @param list<string> $appNames
     */
    private function fetchApps(array $appNames, Context $context): AppCollection
    {
        $criteria = new Criteria();

        if (\count($appNames) === 1) {
            $criteria->addFilter(new EqualsFilter('name', $appNames[0]));
        } else {
            $criteria->addFilter(new EqualsAnyFilter('name', $appNames));
        }

        return $this->appRepository->search($criteria, $context)->getEntities();
    }
}
