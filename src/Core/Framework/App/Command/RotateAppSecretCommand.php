<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\AppSecretRotationService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
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
    description: 'Rotate the shared app secret and integration credentials for an app.',
)]
#[Package('framework')]
class RotateAppSecretCommand extends Command
{
    /**
     * @param EntityRepository<AppCollection> $appRepository
     */
    public function __construct(
        private readonly EntityRepository $appRepository,
        private readonly AppSecretRotationService $rotationService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'The name of the app');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);

        $name = $input->getArgument('name');

        if (!\is_string($name)) {
            throw new \InvalidArgumentException('Argument $name must be a string');
        }

        $context = Context::createCLIContext();

        $app = $this->getAppByName($name, $context);

        if ($app === null) {
            $io->error(\sprintf('No app with name "%s" installed.', $name));

            return self::FAILURE;
        }

        try {
            $this->rotationService->rotateNow($app, $context, AppSecretRotationService::TRIGGER_CLI);
            $io->success(\sprintf('Successfully rotated secrets for app "%s".', $app->getName()));

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $io->error(\sprintf(
                'Failed to rotate secrets for app "%s": %s',
                $app->getName(),
                $exception->getMessage()
            ));

            return self::FAILURE;
        }
    }

    private function getAppByName(string $name, Context $context): ?AppEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $name));

        return $this->appRepository->search($criteria, $context)->getEntities()->first();
    }
}
