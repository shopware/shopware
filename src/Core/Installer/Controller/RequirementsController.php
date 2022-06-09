<?php declare(strict_types=1);

namespace Shopware\Core\Installer\Controller;

use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Installer\Requirements\RequirementsValidatorInterface;
use Shopware\Core\Installer\Requirements\Struct\RequirementsCheckCollection;
use Shopware\Core\Maintenance\System\Service\JwtCertificateGenerator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @internal
 */
class RequirementsController extends InstallerController
{
    /**
     * @var iterable|RequirementsValidatorInterface[]
     */
    private iterable $validators;

    private JwtCertificateGenerator $jwtCertificateGenerator;

    private string $jwtDir;

    /**
     * @param iterable|RequirementsValidatorInterface[] $validators
     */
    public function __construct(iterable $validators, JwtCertificateGenerator $jwtCertificateGenerator, string $projectDir)
    {
        $this->validators = $validators;
        $this->jwtCertificateGenerator = $jwtCertificateGenerator;
        $this->jwtDir = $projectDir . '/config/jwt';
    }

    /**
     * @Since("6.4.13.0")
     * @Route("/installer/requirements", name="installer.requirements", methods={"GET", "POST"})
     */
    public function requirements(Request $request): Response
    {
        $checks = new RequirementsCheckCollection();

        foreach ($this->validators as $validator) {
            $checks = $validator->validateRequirements($checks);
        }

        if ($request->isMethod('POST') && !$checks->hasError()) {
            // The JWT dir exist and is writable, so we generate a new key pair
            $this->jwtCertificateGenerator->generate(
                $this->jwtDir . '/private.pem',
                $this->jwtDir . '/public.pem'
            );

            return $this->redirectToRoute('installer.license');
        }

        return $this->renderInstaller('@Installer/installer/requirements.html.twig', ['requirementChecks' => $checks]);
    }
}
