<?php declare(strict_types=1);

namespace Shopware\Core\Installer\Controller;

use Doctrine\DBAL\Exception\DriverException;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Installer\Database\BlueGreenDeploymentService;
use Shopware\Core\Maintenance\System\Exception\DatabaseSetupException;
use Shopware\Core\Maintenance\System\Service\DatabaseConnectionFactory;
use Shopware\Core\Maintenance\System\Service\JwtCertificateGenerator;
use Shopware\Core\Maintenance\System\Service\SetupDatabaseAdapter;
use Shopware\Core\Maintenance\System\Struct\DatabaseConnectionInformation;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @package core
 *
 * @internal
 */
class DatabaseConfigurationController extends InstallerController
{
    private TranslatorInterface $translator;

    private BlueGreenDeploymentService $blueGreenDeploymentService;

    private JwtCertificateGenerator $jwtCertificateGenerator;

    private SetupDatabaseAdapter $setupDatabaseAdapter;

    private DatabaseConnectionFactory $connectionFactory;

    private string $jwtDir;

    public function __construct(
        TranslatorInterface $translator,
        BlueGreenDeploymentService $blueGreenDeploymentService,
        JwtCertificateGenerator $jwtCertificateGenerator,
        SetupDatabaseAdapter $setupDatabaseAdapter,
        DatabaseConnectionFactory $connectionFactory,
        string $projectDir
    ) {
        $this->translator = $translator;
        $this->blueGreenDeploymentService = $blueGreenDeploymentService;
        $this->jwtCertificateGenerator = $jwtCertificateGenerator;
        $this->setupDatabaseAdapter = $setupDatabaseAdapter;
        $this->connectionFactory = $connectionFactory;
        $this->jwtDir = $projectDir . '/config/jwt';
    }

    /**
     * @Since("6.4.15.0")
     * @Route("/installer/database-configuration", name="installer.database-configuration", methods={"POST", "GET"})
     */
    public function databaseConfiguration(Request $request): Response
    {
        $session = $request->getSession();
        $connectionInfo = $session->get(DatabaseConnectionInformation::class) ?? new DatabaseConnectionInformation();

        if ($request->isMethod('GET')) {
            return $this->renderInstaller('@Installer/installer/database-configuration.html.twig', [
                'connectionInfo' => $connectionInfo,
                'error' => null,
            ]);
        }

        $connectionInfo = (new DatabaseConnectionInformation())->assign($request->request->all());

        try {
            try {
                // check connection
                $connection = $this->connectionFactory->getConnection($connectionInfo);
            } catch (DriverException $e) {
                // Unknown database https://dev.mysql.com/doc/refman/8.0/en/server-error-reference.html#error_er_bad_db_error
                if ($e->getCode() !== 1049) {
                    throw $e;
                }

                $connection = $this->connectionFactory->getConnection($connectionInfo, true);

                $this->setupDatabaseAdapter->createDatabase($connection, $connectionInfo->getDatabaseName());

                $connection = $this->connectionFactory->getConnection($connectionInfo);
            }

            $session->set(DatabaseConnectionInformation::class, $connectionInfo);

            $this->blueGreenDeploymentService->setEnvironmentVariable($connection, $session);

            if ($this->setupDatabaseAdapter->getTableCount($connection, $connectionInfo->getDatabaseName())) {
                $connectionInfo->setDatabaseName('');

                return $this->renderInstaller('@Installer/installer/database-configuration.html.twig', [
                    'connectionInfo' => $connectionInfo,
                    'error' => $this->translator->trans('shopware.installer.database-configuration_non_empty_database'),
                ]);
            }

            $this->jwtCertificateGenerator->generate(
                $this->jwtDir . '/private.pem',
                $this->jwtDir . '/public.pem'
            );
        } catch (DatabaseSetupException $e) {
            return $this->renderInstaller('@Installer/installer/database-configuration.html.twig', [
                'connectionInfo' => $connectionInfo,
                'error' => $this->translator->trans('shopware.installer.database-configuration_error_required_fields'),
            ]);
        } catch (\Exception $e) {
            return $this->renderInstaller('@Installer/installer/database-configuration.html.twig', [
                'connectionInfo' => $connectionInfo,
                'error' => $e->getMessage(),
            ]);
        }

        return $this->redirectToRoute('installer.database-import');
    }

    /**
     * @Since("6.4.15.0")
     * @Route("/installer/database-information", name="installer.database-information", methods={"POST"})
     */
    public function databaseInformation(Request $request): JsonResponse
    {
        $connectionInfo = (new DatabaseConnectionInformation())->assign($request->request->all());

        try {
            $connection = $this->connectionFactory->getConnection($connectionInfo, true);
        } catch (\Exception $e) {
            return new JsonResponse();
        }

        // No need for listing the following schemas
        $ignoredSchemas = ['information_schema', 'performance_schema', 'sys', 'mysql'];
        $databaseNames = $this->setupDatabaseAdapter->getExistingDatabases($connection, $ignoredSchemas);

        $result = [];
        foreach ($databaseNames as $databaseName) {
            $result[$databaseName] = $this->setupDatabaseAdapter->getTableCount($connection, $databaseName) > 0;
        }

        return new JsonResponse($result);
    }
}
