<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Requirement;

use Composer\IO\NullIO;
use Composer\Package\Link;
use Composer\Package\PackageInterface;
use Composer\Semver\Constraint\Constraint;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Composer\Factory;
use Shopware\Core\Framework\Plugin\Composer\PackageProvider;
use Shopware\Core\Framework\Plugin\Exception\PluginComposerJsonInvalidException;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\Requirement\Exception\MissingRequirementException;
use Shopware\Core\Framework\Plugin\Requirement\Exception\RequirementStackException;
use Shopware\Core\Framework\Plugin\Requirement\Exception\VersionMismatchException;

class RequirementsValidator
{
    /**
     * @var EntityRepositoryInterface
     */
    private $pluginRepo;

    /**
     * @var string
     */
    private $projectDir;

    public function __construct(EntityRepositoryInterface $pluginRepo, string $projectDir)
    {
        $this->pluginRepo = $pluginRepo;
        $this->projectDir = $projectDir;
    }

    /**
     * @throws PluginComposerJsonInvalidException
     * @throws RequirementStackException
     */
    public function validateRequirements(Plugin $pluginBaseClass, Context $context, string $method): void
    {
        $exceptionStack = new RequirementExceptionStack();

        $pluginRequirements = $this->getPluginRequirements($pluginBaseClass);

        $pluginRequirements = $this->validateComposerPackages($pluginRequirements, $exceptionStack);
        $pluginRequirements = $this->validateInstalledPlugins($context, $pluginRequirements, $exceptionStack);

        $this->addRemainingRequirementsAsException($pluginRequirements, $exceptionStack);

        $exceptionStack->tryToThrow($method);
    }

    /**
     * @throws PluginComposerJsonInvalidException
     *
     * @return Link[]
     */
    private function getPluginRequirements(Plugin $pluginBaseClass): array
    {
        $pluginInformation = (new PackageProvider())->getPluginInformation($pluginBaseClass->getPath(), new NullIO());

        return $pluginInformation->getRequires();
    }

    /**
     * @param Link[] $pluginRequirements
     *
     * @return Link[]
     */
    private function validateComposerPackages(
        array $pluginRequirements,
        RequirementExceptionStack $exceptionStack
    ): array {
        foreach ($this->getInstalledVendorPackages() as $installedPackage) {
            $pluginRequirements = $this->checkRequirement(
                $pluginRequirements,
                $installedPackage->getName(),
                $installedPackage->getVersion(),
                $exceptionStack
            );
        }

        return $pluginRequirements;
    }

    /**
     * @return PackageInterface[]
     */
    private function getInstalledVendorPackages(): array
    {
        return Factory::createComposer($this->projectDir)
            ->getRepositoryManager()
            ->getLocalRepository()
            ->getPackages();
    }

    /**
     * @param Link[] $pluginRequirements
     *
     * @return Link[]
     */
    private function validateInstalledPlugins(
        Context $context,
        array $pluginRequirements,
        RequirementExceptionStack $exceptionStack
    ): array {
        /** @var PluginEntity $pluginEntity */
        foreach ($this->getInstalledPlugins($context) as $pluginEntity) {
            $pluginRequirements = $this->checkRequirement(
                $pluginRequirements,
                $pluginEntity->getComposerName(),
                $pluginEntity->getVersion(),
                $exceptionStack
            );
        }

        return $pluginRequirements;
    }

    private function getInstalledPlugins(Context $context): PluginCollection
    {
        /** @var PluginCollection $plugins */
        $plugins = $this->pluginRepo->search(new Criteria(), $context)->getEntities();

        return $plugins;
    }

    /**
     * @param Link[] $pluginRequirements
     *
     * @return Link[]
     */
    private function checkRequirement(
        array $pluginRequirements,
        string $installedName,
        string $installedVersion,
        RequirementExceptionStack $exceptionStack
    ): array {
        if (isset($pluginRequirements[$installedName])) {
            $constraint = $pluginRequirements[$installedName]->getConstraint();
            if ($constraint === null) {
                return $pluginRequirements;
            }

            if ($constraint->matches(new Constraint('==', $installedVersion)) === false) {
                $exceptionStack->add(
                    new VersionMismatchException($installedName, $constraint->getPrettyString(), $installedVersion)
                );
            }

            unset($pluginRequirements[$installedName]);
        }

        return $pluginRequirements;
    }

    /**
     * @param Link[] $pluginRequirements
     */
    private function addRemainingRequirementsAsException(
        array $pluginRequirements,
        RequirementExceptionStack $exceptionStack
    ): void {
        foreach ($pluginRequirements as $installedPackage => $requirement) {
            $exceptionStack->add(
                new MissingRequirementException($installedPackage, $requirement->getPrettyConstraint())
            );
        }
    }
}
