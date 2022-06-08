<?php declare(strict_types=1);

namespace Shopware\Core\Installer\Requirements;

use Composer\Repository\PlatformRepository;
use Shopware\Core\Framework\Plugin\Composer\Factory;
use Shopware\Core\Installer\Requirements\Struct\RequirementCheck;
use Shopware\Core\Installer\Requirements\Struct\RequirementsCheckCollection;
use Shopware\Core\Installer\Requirements\Struct\SystemCheck;

/**
 * @internal
 */
class EnvironmentRequirementsValidator implements RequirementsValidatorInterface
{
    private PlatformRepository $systemEnvironment;

    private string $projectDir;

    public function __construct(PlatformRepository $systemEnvironment, string $projectDir)
    {
        $this->systemEnvironment = $systemEnvironment;
        $this->projectDir = $projectDir;
    }

    public function validateRequirements(RequirementsCheckCollection $checks): RequirementsCheckCollection
    {
        $composer = Factory::createComposer($this->projectDir);
        $platform = $composer->getRepositoryManager()->getLocalRepository()->findPackage('shopware/platform', '*');
        if (!$platform) {
            $platform = $composer->getRepositoryManager()->getLocalRepository()->findPackage('shopware/core', '*');
        }
        if (!$platform) {
            $platform = $composer->getPackage();
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
                    $result->getVersion()
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
                $extension->getVersion()
            ));
        }

        return $checks;
    }
}
