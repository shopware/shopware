<?php declare(strict_types=1);

namespace Shopware\Core\Installer\Controller;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Package('framework')]
class TranslationController extends InstallerController
{
    #[Route(path: '/installer/translation', name: 'installer.translation', methods: ['GET'])]
    public function translations(Request $request): Response
    {
        return $this->renderInstaller('@Installer/installer/translation.html.twig', [
            'supportedLanguages' => [], // disable language switch during translation step
        ]);
    }

    #[Route(path: '/installer/translation/run', name: 'installer.translation-run', methods: ['POST'])]
    public function run(Request $request): JsonResponse
    {
        $session = $request->getSession();

        /** @var list<string> $locales */
        $locales = (array) $session->get('SELECTED_LANGUAGES', []);

        if (empty($locales)) {
            return new JsonResponse([
                'isFinished' => true,
                'failed' => false,
            ]);
        }

        $projectRoot = \dirname(__DIR__, 4);
        $console = $projectRoot . '/bin/console';

        $proc = new Process(
            [$console, 'translation:install', '--locales=' . implode(',', $locales), '--no-interaction'],
            $projectRoot
        );
        $proc->setTimeout(1200);
        $proc->run();

        if (!$proc->isSuccessful()) {
            return new JsonResponse([
                'isFinished' => true,
                'failed' => true,
            ], 200);
        }

        return new JsonResponse([
            'isFinished' => true,
            'failed' => false,
        ]);
    }
}
