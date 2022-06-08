<?php declare(strict_types=1);

namespace Shopware\Core\Installer\Controller;

use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Installer\Requirements\RequirementsValidatorInterface;
use Shopware\Core\Installer\Requirements\Struct\RequirementsCheckCollection;
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

    /**
     * @param iterable|RequirementsValidatorInterface[] $validators
     */
    public function __construct(iterable $validators)
    {
        $this->validators = $validators;
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
            return $this->redirectToRoute('installer.license');
        }

        return $this->renderInstaller('@Installer/installer/requirements.html.twig', ['requirementChecks' => $checks]);
    }
}
