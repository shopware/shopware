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
        $payload = json_decode((string) $request->getContent(), true) ?: [];
        $offset  = (int) ($payload['offset'] ?? 0);

        $session = $request->getSession();

        // Clear old failures when starting a new translation run
        if ($offset === 0) {
            $session->remove('TRANSLATION_FAILED');
        }

        /** @var list<string> $locales */
        $locales = (array) $session->get('SELECTED_LANGUAGES', []);
        if (!$locales) {
            $locales = (array) $session->get('installer.locales', []);
        }

        $total = \count($locales);

        if ($total === 0 || $offset >= $total) {
            return new JsonResponse([
                'offset'     => $total,
                'total'      => $total,
                'isFinished' => true,
                'message'    => $total === 0 ? 'No locales selected' : 'Done',
                'skipped'    => false,
                'failures'   => (array) $session->get('TRANSLATION_FAILED', []),
            ]);
        }

        $locale      = $locales[$offset];
        $projectRoot = \dirname(__DIR__, 4);
        $console     = $projectRoot . '/bin/console';

        $next = $offset + 1;

        $proc = new Process(
            [$console, 'translation:install', '--locales=' . $locale, '--no-interaction'],
            $projectRoot
        );
        $proc->setTimeout(600);
        $proc->run();

        if (!$proc->isSuccessful()) {
            $err = trim($proc->getErrorOutput() ?: $proc->getOutput());

            // TODO: maybe remove?
            $cleanError = $this->cleanErrorOutput($err, $locale);

            // collect failure but DO NOT stop; advance to next
            $failures = $session->get('TRANSLATION_FAILED', []);
            $failures[] = ['locale' => $locale, 'error' => $cleanError];
            $session->set('TRANSLATION_FAILED', $failures);

            return new JsonResponse([
                'offset'     => $next,
                'total'      => $total,
                'isFinished' => $next >= $total,
                'message'    => $locale,
                'skipped'    => true,
                'error'      => $cleanError,
                'failures'   => $next >= $total ? $failures : [],
            ], 200);
        }

        return new JsonResponse([
            'offset'     => $next,
            'total'      => $total,
            'isFinished' => $next >= $total,
            'message'    => $locale,
            'skipped'    => false,
            'failures'   => $next >= $total ? (array) $session->get('TRANSLATION_FAILED', []) : [],
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
