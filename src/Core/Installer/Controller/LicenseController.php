<?php declare(strict_types=1);

namespace Shopware\Core\Installer\Controller;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Installer\License\LicenseFetcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @internal
 */
#[Package('core')]
class LicenseController extends InstallerController
{
    public function __construct(private readonly LicenseFetcher $licenseFetcher)
    {
    }

    #[Route(path: '/installer/license', name: 'installer.license', methods: ['GET', 'POST'])]
    public function license(Request $request): Response
    {
        if ($request->isMethod('POST') && $request->request->get('tos', false)) {
            return $this->redirectToRoute('installer.database-configuration');
        }

        $error = null;
        $licenseAgreement = null;

        try {
            $licenseAgreement = $this->licenseFetcher->fetch($request);
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }

        return $this->renderInstaller(
            '@Installer/installer/license.html.twig',
            [
                'licenseAgreement' => $licenseAgreement,
                'error' => $error,
            ]
        );
    }
}
