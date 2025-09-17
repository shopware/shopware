<?php declare(strict_types=1);

namespace Shopware\Core\Installer\Controller;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Maintenance\System\Struct\DatabaseConnectionInformation;
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
    private const TRANSLATION_TIMEOUT_SECONDS = 300;
    private const LOCALE_PATTERN = '/^[a-z]{2}(-[A-Z]{2})?$/';

    public function __construct(private readonly string $projectDir)
    {
    }

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

        /** @var DatabaseConnectionInformation|null $connectionInfo */
        $connectionInfo = $session->get(DatabaseConnectionInformation::class);

        // Validate locales to prevent command injection
        $locales = $this->sanitizeLocales($locales);

        $console = $this->projectDir . '/bin/console';

        $env = [
            'DATABASE_URL' => $connectionInfo ? $connectionInfo->asDsn() : getenv('DATABASE_URL'),
        ];

        $proc = new Process(
            [$console, 'translation:install', '--locales=' . implode(',', $locales), '--no-interaction'],
            $this->projectDir,
            $env
        );
        $proc->setTimeout(self::TRANSLATION_TIMEOUT_SECONDS);
        $proc->run();

        $session->remove(DatabaseConnectionInformation::class);

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

    /**
     * Sanitize and validate locales
     *
     * @param list<string> $locales
     *
     * @return list<string>
     */
    private function sanitizeLocales(array $locales): array
    {
        return array_values(array_unique(array_filter(
            $locales,
            static function (string $locale): bool {
                return \preg_match(self::LOCALE_PATTERN, $locale) === 1;
            }
        )));
    }
}
