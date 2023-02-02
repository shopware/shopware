<?php declare(strict_types=1);

namespace Shopware\Core\Installer\Requirements;

use Composer\Composer;
use Composer\Repository\PlatformRepository;
use Shopware\Core\Installer\Requirements\Struct\RequirementCheck;
use Shopware\Core\Installer\Requirements\Struct\RequirementsCheckCollection;
use Shopware\Core\Installer\Requirements\Struct\SystemCheck;

/**
 * @internal
 */
class EnvironmentRequirementsValidator implements RequirementsValidatorInterface
{
    private Composer $composer;

    private PlatformRepository $systemEnvironment;

    public function __construct(Composer $composer, PlatformRepository $systemEnvironment)
    {
        $this->composer = $composer;
        $this->systemEnvironment = $systemEnvironment;
    }

    public function validateRequirements(RequirementsCheckCollection $checks): RequirementsCheckCollection
    {
        $platform = $this->composer->getRepositoryManager()->getLocalRepository()->findPackage('shopware/platform', '*');
        if (!$platform) {
            $platform = $this->composer->getRepositoryManager()->getLocalRepository()->findPackage('shopware/core', '*');
        }
        if (!$platform) {
            $platform = $this->composer->getPackage();
        }

        foreach ($platform->getRequires() as $require => $link) {
            if (!PlatformRepository::isPlatformPackage($require)) {
                continue;
            }

            $result = $this->systemEnvironment->findPackage($require, $link->getConstraint());

            if ($result) {
                $checks->add(new SystemCheck(
                    $require,
                    RequirementCheck::STATUS_SUCCESS,
                    $link->getConstraint()->getPrettyString(),
                    $result->getPrettyVersion()
                ));

                continue;
            }

            $extension = $this->systemEnvironment->findPackage($require, '*');

            if ((string) $link->getConstraint() === '*' || !$extension) {
                $checks->add(new SystemCheck(
                    $require,
                    RequirementCheck::STATUS_ERROR,
                    $link->getConstraint()->getPrettyString(),
                    '-'
                ));

                continue;
            }

            $checks->add(new SystemCheck(
                $require,
                RequirementCheck::STATUS_ERROR,
                $link->getConstraint()->getPrettyString(),
                $extension->getPrettyVersion()
            ));
        }

        return $checks;
    }
}
