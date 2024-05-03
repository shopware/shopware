<?php
declare(strict_types=1);

namespace Shopware\WebInstaller\Controller;

use Shopware\Core\Framework\Log\Package;
use Shopware\WebInstaller\Services\FileBackup;
use Shopware\WebInstaller\Services\FlexMigrator;
use Shopware\WebInstaller\Services\PluginCompatibility;
use Shopware\WebInstaller\Services\ProjectComposerJsonUpdater;
use Shopware\WebInstaller\Services\RecoveryManager;
use Shopware\WebInstaller\Services\ReleaseInfoProvider;
use Shopware\WebInstaller\Services\StreamedCommandResponseGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Package('core')]
class UpdateController extends AbstractController
{
    public function __construct(
        private readonly RecoveryManager $recoveryManager,
        private readonly ReleaseInfoProvider $releaseInfoProvider,
        private readonly FlexMigrator $flexMigrator,
        private readonly StreamedCommandResponseGenerator $streamedCommandResponseGenerator,
        private readonly ProjectComposerJsonUpdater $projectComposerJsonUpdater
    ) {
    }

    #[Route('/update', name: 'update', defaults: ['step' => 2], methods: ['GET'])]
    public function index(Request $request): Response
    {
        try {
            $shopwarePath = $this->recoveryManager->getShopwareLocation();
        } catch (\RuntimeException) {
            return $this->redirectToRoute('configure');
        }

        $currentShopwareVersion = $this->recoveryManager->getCurrentShopwareVersion($shopwarePath);
        $latestVersions = $this->getLatestVersions($request);

        if (empty($latestVersions)) {
            return $this->redirectToRoute('finish');
        }

        return $this->render('update.html.twig', [
            'shopwarePath' => $shopwarePath,
            'currentShopwareVersion' => $currentShopwareVersion,
            'isFlexProject' => $this->recoveryManager->isFlexProject($shopwarePath),
            'versions' => $latestVersions,
        ]);
    }

    #[Route('/update/_migrate-template', name: 'migrate-template', methods: ['POST'])]
    public function migrateTemplate(): Response
    {
        $shopwarePath = $this->recoveryManager->getShopwareLocation();

        $this->flexMigrator->cleanup($shopwarePath);
        $this->flexMigrator->patchRootComposerJson($shopwarePath);
        $this->flexMigrator->copyNewTemplateFiles($shopwarePath);
        $this->flexMigrator->migrateEnvFile($shopwarePath);

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    #[Route('/update/_run', name: 'update_run', methods: ['POST'])]
    public function run(Request $request): Response
    {
        $version = $request->query->get('shopwareVersion', '');

        $shopwarePath = $this->recoveryManager->getShopwareLocation();
        $composerJsonPath = $shopwarePath . '/composer.json';

        $composerJsonBackup = new FileBackup($composerJsonPath);
        $composerJsonBackup->backup();

        $pluginCompat = new PluginCompatibility($composerJsonPath, $version);
        $pluginCompat->removeIncompatible();

        $this->projectComposerJsonUpdater->update($composerJsonPath, $version);

        return $this->streamedCommandResponseGenerator->runJSON([
            $this->recoveryManager->getPhpBinary($request),
            '-dmemory_limit=1G',
            $this->recoveryManager->getBinary(),
            'update',
            '-d',
            $shopwarePath,
            '--no-interaction',
            '--no-ansi',
            '--no-scripts',
            '-v',
            '--with-all-dependencies', // update all packages
        ], function (Process $process) use ($composerJsonBackup): void {
            $process->isSuccessful()
                ? $composerJsonBackup->remove()
                : $composerJsonBackup->restore();
        });
    }

    #[Route('/update/_reset_config', name: 'update_reset_config', methods: ['POST'])]
    public function resetConfig(Request $request): Response
    {
        if (\function_exists('opcache_reset')) {
            opcache_reset();
        }

        $shopwarePath = $this->recoveryManager->getShopwareLocation();

        $this->patchSymfonyFlex($shopwarePath);

        return $this->streamedCommandResponseGenerator->runJSON([
            $this->recoveryManager->getPhpBinary($request),
            '-dmemory_limit=1G',
            $this->recoveryManager->getBinary(),
            '-d',
            $shopwarePath,
            'symfony:recipes:install',
            '--force',
            '--reset',
            '--no-interaction',
            '--no-ansi',
            '-v',
        ]);
    }

    #[Route('/update/_prepare', name: 'update_prepare', methods: ['POST'])]
    public function prepare(Request $request): Response
    {
        $shopwarePath = $this->recoveryManager->getShopwareLocation();

        return $this->streamedCommandResponseGenerator->runJSON([
            $this->recoveryManager->getPhpBinary($request),
            '-dmemory_limit=1G',
            $shopwarePath . '/bin/console',
            'system:update:prepare',
            '--no-interaction',
        ]);
    }

    #[Route('/update/_finish', name: 'update_finish', methods: ['POST'])]
    public function finish(Request $request): Response
    {
        $shopwarePath = $this->recoveryManager->getShopwareLocation();

        return $this->streamedCommandResponseGenerator->runJSON([
            $this->recoveryManager->getPhpBinary($request),
            '-dmemory_limit=1G',
            $shopwarePath . '/bin/console',
            'system:update:finish',
            '--no-interaction',
        ]);
    }

    /**
     * @see https://github.com/symfony/flex/pull/963
     */
    public function patchSymfonyFlex(string $shopwarePath): void
    {
        $optionsPhp = (string) file_get_contents($shopwarePath . '/vendor/symfony/flex/src/Options.php');

        $optionsPhp = str_replace(
            'return $this->io && $this->io->askConfirmation(sprintf(\'Cannot determine the state of the "%s" file, overwrite anyway? [y/N] \', $file), false);',
            'return $this->io && $this->io->askConfirmation(sprintf(\'Cannot determine the state of the "%s" file, overwrite anyway? [y/N] \', $file));',
            $optionsPhp
        );

        $optionsPhp = str_replace(
            'return $this->io && $this->io->askConfirmation(sprintf(\'File "%s" has uncommitted changes, overwrite? [y/N] \', $name), false);',
            'return $this->io && $this->io->askConfirmation(sprintf(\'File "%s" has uncommitted changes, overwrite? [y/N] \', $name));',
            $optionsPhp
        );

        file_put_contents($shopwarePath . '/vendor/symfony/flex/src/Options.php', $optionsPhp);
    }

    /**
     * @return array<string>
     */
    private function getLatestVersions(Request $request): array
    {
        if ($request->getSession()->has('latestVersions')) {
            $sessionValue = $request->getSession()->get('latestVersions');
            \assert(\is_array($sessionValue));

            return $sessionValue;
        }

        $shopwarePath = $this->recoveryManager->getShopwareLocation();

        $currentVersion = $this->recoveryManager->getCurrentShopwareVersion($shopwarePath);
        $latestVersions = $this->releaseInfoProvider->fetchUpdateVersions($currentVersion);

        $request->getSession()->set('latestVersions', $latestVersions);

        return $latestVersions;
    }
}
