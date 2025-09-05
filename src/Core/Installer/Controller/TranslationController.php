<?php declare(strict_types=1);

namespace Shopware\Core\Installer\Controller;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Attribute\Route;

#[Package('framework')]
class TranslationController extends InstallerController
{
    #[Route(path: '/installer/translation', name: 'installer.translation', methods: ['GET'])]
    public function translations(Request $request): Response
    {
        $session = $request->getSession();

        /** @var list<string> $locales */
        $locales = (array) $session->get('SELECTED_LANGUAGES', []);
        if (!$locales) {
            $locales = (array) $session->get('installer.locales', []);
        }

        return $this->renderInstaller('@Installer/installer/translation.html.twig', [
            'locales' => $locales,
            'total'   => \count($locales),
            'supportedLanguages' => [], // disable language switch during translation step
        ]);
    }

    #[Route(path: '/installer/translation/run', name: 'installer.translation-run', methods: ['POST'])]
    public function run(Request $request): JsonResponse
    {
        $session = $request->getSession();

        // Clear old failures when starting a new translation run
        $session->remove('TRANSLATION_FAILED');

        /** @var list<string> $locales */
        $locales = (array) $session->get('SELECTED_LANGUAGES', []);
        if (!$locales) {
            $locales = (array) $session->get('installer.locales', []);
        }

        $total = \count($locales);

        if ($total === 0) {
            return new JsonResponse([
                'offset'     => 0,
                'total'      => 0,
                'isFinished' => true,
                'message'    => 'No locales selected',
                'skipped'    => false,
                'failures'   => [],
            ]);
        }

        $projectRoot = \dirname(__DIR__, 4);
        $console     = $projectRoot . '/bin/console';

        $proc = new Process(
            [$console, 'translation:install', '--locales=' . implode(',', $locales), '--no-interaction'],
            $projectRoot
        );
        $proc->setTimeout(1200);
        $proc->run();

        if (!$proc->isSuccessful()) {
            $err = trim($proc->getErrorOutput() ?: $proc->getOutput());
            $cleanError = $this->cleanErrorOutput($err, implode(',', $locales));

            $failures = [['locales' => $locales, 'error' => $cleanError]];
            $session->set('TRANSLATION_FAILED', $failures);

            return new JsonResponse([
                'offset'     => $total,
                'total'      => $total,
                'isFinished' => true,
                'message'    => 'Installation failed',
                'skipped'    => true,
                'error'      => $cleanError,
                'failures'   => $failures,
            ], 200);
        }

        return new JsonResponse([
            'offset'     => $total,
            'total'      => $total,
            'isFinished' => true,
            'message'    => 'All languages installed successfully',
            'skipped'    => false,
            'failures'   => [],
        ]);
    }

    /**
     * Clean up error output
     */
    private function cleanErrorOutput(string $error, string $locale): string
    {
        if (strpos($error, 'Invalid locale codes:') !== false) {
            return "Invalid locale code";
        }
    }
}
