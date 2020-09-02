<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Requirement;

use Composer\Composer;
use Composer\Package\Link;
use Composer\Repository\PlatformRepository;
use Composer\Semver\Constraint\Constraint;
use Composer\Semver\VersionParser;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Plugin\Composer\Factory;
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

    /**
     * @var Composer
     */
    private $pluginComposer;

    public function __construct(EntityRepositoryInterface $pluginRepo, string $projectDir)
    {
        $this->pluginRepo = $pluginRepo;
        $this->projectDir = $projectDir;
    }

    /**
     * @throws RequirementStackException
     */
    public function validateRequirements(PluginEntity $plugin, Context $context, string $method): void
    {
        $exceptionStack = new RequirementExceptionStack();

        $pluginRequirements = $this->getPluginRequirements($plugin);

        $pluginRequirements = $this->validateComposerPackages($pluginRequirements, $exceptionStack);
        $pluginRequirements = $this->validateInstalledPlugins($context, $pluginRequirements, $exceptionStack);
        $pluginRequirements = $this->validateShippedDependencies($plugin, $pluginRequirements, $exceptionStack);

        $this->addRemainingRequirementsAsException($pluginRequirements, $exceptionStack);

        $exceptionStack->tryToThrow($method);
    }

    /**
     * resolveActiveDependants returns all active dependants of the given plugin.
     *
     * @param PluginEntity[] $dependants the plugins to check for a dependency on the given plugin
     *
     * @return PluginEntity[]
     */
    public function resolveActiveDependants(PluginEntity $dependency, array $dependants): array
    {
        return array_filter($dependants, function ($dependant) use ($dependency) {
            if (!$dependant->getActive()) {
                return false;
            }

            return $this->dependsOn($dependant, $dependency);
        });
    }

    /**
     * dependsOn determines, wether a given plugin depends on another one.
     *
     * @param PluginEntity $plugin     the plugin to be checked
     * @param PluginEntity $dependency the potential dependency
     */
    private function dependsOn(PluginEntity $plugin, PluginEntity $dependency): bool
    {
        foreach (array_keys($this->getPluginRequirements($plugin)) as $requirement) {
            if ($requirement === $dependency->getComposerName()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return Link[]
     */
    private function getPluginRequirements(PluginEntity $plugin): array
    {
        $this->pluginComposer = $this->getComposer($this->projectDir . '/' . $plugin->getPath());

        return $this->pluginComposer->getPackage()->getRequires();
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
        $shopwareProjectComposer = $this->getComposer($this->projectDir);
        $pluginRequirements = $this->checkComposerDependencies(
            $pluginRequirements,
            $exceptionStack,
            $shopwareProjectComposer
        );

        return $pluginRequirements;
    }

    private function getComposer(string $composerPath): Composer
    {
        return Factory::createComposer($composerPath);
    }

    /**
     * @param Link[] $pluginRequirements
     *
     * @return Link[]
     */
    private function checkComposerDependencies(
        array $pluginRequirements,
        RequirementExceptionStack $exceptionStack,
        Composer $pluginComposer
    ): array {
        $packages = $pluginComposer->getRepositoryManager()->getLocalRepository()->getPackages();

        // Get PHP extension "packages"
        $packages = array_merge($packages, (new PlatformRepository())->getPackages());

        foreach ($packages as $package) {
            $pluginRequirements = $this->checkRequirement(
                $pluginRequirements,
                $package->getName(),
                $package->getVersion(),
                $exceptionStack
            );

            foreach ($package->getReplaces() as $replace) {
                $replaceVersion = $replace->getPrettyConstraint();

                if ($replaceVersion === 'self.version') {
                    $replaceVersion = $package->getVersion();
                }

                $pluginRequirements = $this->checkRequirement(
                    $pluginRequirements,
                    $replace->getTarget(),
                    $replaceVersion,
                    $exceptionStack
                );
            }
        }

        return $pluginRequirements;
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
        $parser = new VersionParser();
        foreach ($this->getInstalledPlugins($context) as $pluginEntity) {
            $pluginRequirements = $this->checkRequirement(
                $pluginRequirements,
                $pluginEntity->getComposerName(),
                $parser->normalize($pluginEntity->getVersion()),
                $exceptionStack
            );
        }

        return $pluginRequirements;
    }

    private function getInstalledPlugins(Context $context): PluginCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [new EqualsFilter('installedAt', null)]));
        $criteria->addFilter(new EqualsFilter('active', true));
        /** @var PluginCollection $plugins */
        $plugins = $this->pluginRepo->search($criteria, $context)->getEntities();

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

    /**
     * @param Link[] $pluginRequirements
     *
     * @return Link[]
     */
    private function validateShippedDependencies(
        PluginEntity $plugin,
        array $pluginRequirements,
        RequirementExceptionStack $exceptionStack
    ): array {
        if ($plugin->getManagedByComposer()) {
            return $pluginRequirements;
        }

        $vendorDir = $this->pluginComposer->getConfig()->get('vendor-dir');
        if (!is_dir($vendorDir)) {
            return $pluginRequirements;
        }
        $pluginRequirements = $this->checkComposerDependencies(
            $pluginRequirements,
            $exceptionStack,
            $this->pluginComposer
        );

        return $pluginRequirements;
    }
}
