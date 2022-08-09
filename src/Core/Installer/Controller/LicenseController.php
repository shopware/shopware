<?php declare(strict_types=1);

namespace Shopware\Core\Installer\Controller;

use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Installer\License\LicenseFetcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @internal
 */
class LicenseController extends InstallerController
{
    private LicenseFetcher $licenseFetcher;

    public function __construct(LicenseFetcher $licenseFetcher)
    {
        $this->licenseFetcher = $licenseFetcher;
    }

    /**
     * @Since("6.4.15.0")
     * @Route("/installer/license", name="installer.license", methods={"GET", "POST"})
     */
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
