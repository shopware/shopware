<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Command;

use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Lifecycle\AppSecretRotationService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal only for use by the app-system
 */
#[AsCommand(
    name: 'app:secret:recover',
    description: 'Recover an app whose secret rotation left an unconfirmed secret.',
)]
#[Package('framework')]
final readonly class RecoverAppSecretCommand
{
    /**
     * @param EntityRepository<AppCollection> $appRepository
     */
    public function __construct(
        private EntityRepository $appRepository,
        private AppSecretRotationService $rotationService,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'The name of the app to recover. Omit to list apps with an unconfirmed secret.')]
        ?string $name = null,
        #[Option(description: 'Discard the unconfirmed secret for the named app instead of trying recovery. Use this before app:shop-id:change when the app registration is genuinely lost.')]
        bool $discard = false,
    ): int {
        $context = Context::createCLIContext();

        if ($discard && $name === null) {
            $io->error('--discard requires an app name.');

            return Command::FAILURE;
        }

        if ($name === null) {
            return $this->listAppsWithUnconfirmedSecrets($io, $context);
        }

        return $discard
            ? $this->discardApp($io, $context, $name)
            : $this->recoverApp($io, $context, $name);
    }

    private function listAppsWithUnconfirmedSecrets(SymfonyStyle $io, Context $context): int
    {
        $apps = $this->rotationService->findAppsWithUnconfirmedSecrets($context);
        if ($apps->count() === 0) {
            $io->success('No apps have an unconfirmed secret.');

            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($apps as $app) {
            $updatedAt = $app->getUnconfirmedAppSecretsUpdatedAt();
            $rows[] = [$app->getName(), $app->getId(), $updatedAt?->format(\DateTimeInterface::ATOM) ?? 'unknown'];
        }

        $io->table(['App', 'App ID', 'Pending since'], $rows);
        $io->note('Run "app:secret:recover <name>" to re-register an app with an unconfirmed secret.');

        return Command::SUCCESS;
    }

    private function discardApp(SymfonyStyle $io, Context $context, string $name): int
    {
        $app = $this->findAppByName($name, $context);
        if (!$app instanceof AppEntity) {
            $io->error(\sprintf('No app found for "%s".', $name));

            return Command::FAILURE;
        }

        $this->rotationService->discardNow($app->getId(), $context);
        $io->success(\sprintf('Discarded the unconfirmed secret for "%s". Run "app:shop-id:change" to create a new registration.', $app->getName()));

        return Command::SUCCESS;
    }

    private function recoverApp(SymfonyStyle $io, Context $context, string $name): int
    {
        $app = $this->findAppByName($name, $context);
        if (!$app instanceof AppEntity) {
            $io->error(\sprintf('No app found for "%s".', $name));

            return Command::FAILURE;
        }

        try {
            $this->rotationService->recoverNow($app->getId(), $context);
        } catch (AppException $e) {
            if ($e->getErrorCode() === AppException::APP_SECRET_ROTATION_NOTHING_TO_RECOVER) {
                $io->note($e->getMessage());

                return Command::SUCCESS;
            }

            if ($e->getErrorCode() === AppException::APP_SECRET_ROTATION_CLAIMED) {
                $io->error($e->getMessage());

                return Command::FAILURE;
            }

            if ($e->getErrorCode() === AppException::REGISTRATION_FAILED) {
                // The app never confirmed (timeout/5xx), so the outcome is unknown; the operator retries.
                $io->warning(\sprintf('Recovery outcome for "%s" is unknown (the app did not confirm); retry later. %s', $app->getName(), $e->getMessage()));

                return Command::FAILURE;
            }

            // Any other failure (for example, the manifest could not be loaded) is real: report it so a
            // script that retries keeps trying, instead of treating an app that was never recovered as done.
            $io->error(\sprintf('Recovery of "%s" failed: %s', $app->getName(), $e->getMessage()));

            return Command::FAILURE;
        }

        $io->success(\sprintf('Re-registered app "%s" with a fresh secret.', $app->getName()));

        return Command::SUCCESS;
    }

    private function findAppByName(string $name, Context $context): ?AppEntity
    {
        return $this->appRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('name', $name)),
            $context
        )->getEntities()->first();
    }
}
