<?php
declare(strict_types=1);

namespace App\Controller;

use App\Services\ProjectComposerJsonUpdater;
use App\Services\RecoveryManager;
use App\Services\ReleaseInfoProvider;
use App\Services\StreamedCommandResponseGenerator;
use Shopware\Core\Framework\Log\Package;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @internal
 */
#[Package('core')]
class InstallController extends AbstractController
{
    public function __construct(
        private readonly RecoveryManager $recoveryManager,
        private readonly StreamedCommandResponseGenerator $streamedCommandResponseGenerator,
        private readonly ReleaseInfoProvider $releaseInfoProvider
    ) {
    }

    #[Route('/install', name: 'install', defaults: ['step' => 2])]
    public function index(Request $request): Response
    {
        $channel = $request->getSession()->get('channel', 'stable');

        $versions = $this->releaseInfoProvider->fetchInstallVersions($channel === 'rc');

        return $this->render('install.html.twig', [
            'versions' => $versions,
        ]);
    }

    #[Route('/install/_run', name: 'install_run', methods: ['POST'])]
    public function run(Request $request): StreamedResponse
    {
        $shopwareVersion = $request->query->get('shopwareVersion', '6.4.20.0');
        $folder = $this->recoveryManager->getProjectDir();

        $fs = new Filesystem();
        $fs->copy(\dirname(__DIR__) . '/Resources/install-template/composer.json', $folder . '/composer.json');
        $fs->dumpFile($folder . '/.env', \PHP_EOL);
        $fs->dumpFile($folder . '/.gitignore', '/.idea
/vendor/
');
        $fs->mkdir($folder . '/custom/plugins');
        $fs->mkdir($folder . '/custom/static-plugins');

        ProjectComposerJsonUpdater::update(
            $folder . '/composer.json',
            $shopwareVersion,
            $request->getSession()->get('channel', 'stable')
        );

        $finish = function (Process $process) use ($request): void {
            echo json_encode([
                'success' => $process->isSuccessful(),
                'newLocation' => $request->getBasePath() . '/public/',
            ]);
        };

        return $this->streamedCommandResponseGenerator->run([
            $this->recoveryManager->getPhpBinary($request),
            $this->recoveryManager->getBinary(),
            'composer',
            'install',
            '-d',
            $folder,
            '--no-interaction',
            '--no-ansi',
            '-v',
        ], $finish);
    }

    /**
     * @codeCoverageIgnore
     */
    #[Route('/install/_cleanup', name: 'install_cleanup', methods: ['POST'])]
    public function cleanup(): StreamedResponse
    {
        $folder = $this->recoveryManager->getProjectDir();

        $fs = new Filesystem();
        $htaccessFile = $folder . '/public/.htaccess';

        // Shopware 6.4 does not contain a htaccess by default
        if (!$fs->exists($htaccessFile)) {
            $fs = new Filesystem();
            $fs->copy(\dirname(__DIR__) . '/Resources/install-template/htaccess', $htaccessFile);
        }

        $self = $_SERVER['SCRIPT_FILENAME'];
        \assert(\is_string($self));

        // Below this line call only php native functions as we deleted our own files already
        unlink($self);

        if (\function_exists('opcache_reset')) {
            opcache_reset();
        }

        exit();
    }
}
