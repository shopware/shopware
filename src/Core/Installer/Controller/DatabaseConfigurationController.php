<?php declare(strict_types=1);

namespace Shopware\Core\Installer\Controller;

use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Maintenance\System\Struct\DatabaseConnectionInformation;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @internal
 */
class DatabaseConfigurationController extends InstallerController
{
    /**
     * @Since("6.4.13.0")
     * @Route("/installer/database-configuration", name="installer.database-configuration", methods={"GET"})
     */
    public function databaseConfiguration(): Response
    {
        return $this->renderInstaller('@Installer/installer/database-configuration.html.twig', [
            'connectionInfo' => new DatabaseConnectionInformation(),
            'error' => null,
        ]);
    }
}
