<?php declare(strict_types=1);

namespace Shopware\Core\Installer\Controller;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Installer\Database\BlueGreenDeploymentService;
use Shopware\Core\Installer\Database\DatabaseMigrator;
use Shopware\Core\Maintenance\System\Service\DatabaseConnectionFactory;
use Shopware\Core\Maintenance\System\Struct\DatabaseConnectionInformation;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @internal
 */
#[Package('core')]
class DatabaseImportController extends InstallerController
{
    public function __construct(
        private readonly DatabaseConnectionFactory $connectionFactory,
        private readonly DatabaseMigrator $migrator
    ) {
    }

    #[Route(path: '/installer/database-import', name: 'installer.database-import', methods: ['GET'])]
    public function databaseImport(Request $request): Response
    {
        $session = $request->getSession();
        $connectionInfo = $session->get(DatabaseConnectionInformation::class);

        if (!$connectionInfo) {
            return $this->redirectToRoute('installer.database-configuration');
        }

        return $this->renderInstaller(
            '@Installer/installer/database-import.html.twig',
            [
                'error' => null,
                'supportedLanguages' => [], // overwrite language switch, so import can't be aborted due do language switch
            ]
        );
    }

    #[Route(path: '/installer/database-migrate', name: 'installer.database-migrate', methods: ['POST'])]
    public function databaseMigrate(Request $request): JsonResponse
    {
        $session = $request->getSession();
        /** @var DatabaseConnectionInformation|null $connectionInfo */
        $connectionInfo = $session->get(DatabaseConnectionInformation::class);

        if (!$connectionInfo) {
            return new JsonResponse([
                'error' => 'Session expired, please go back to database configuration.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $_SERVER[BlueGreenDeploymentService::ENV_NAME] = $_ENV[BlueGreenDeploymentService::ENV_NAME] = $session->get(BlueGreenDeploymentService::ENV_NAME);
        $_SERVER[MigrationStep::INSTALL_ENVIRONMENT_VARIABLE] = $_ENV[MigrationStep::INSTALL_ENVIRONMENT_VARIABLE] = true;

        try {
            $connection = $this->connectionFactory->getConnection($connectionInfo);

            $offset = json_decode((string) $request->getContent(), true, 512, \JSON_THROW_ON_ERROR)['offset'] ?? 0;
            $result = $this->migrator->migrate($offset, $connection);

            return new JsonResponse($result);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
