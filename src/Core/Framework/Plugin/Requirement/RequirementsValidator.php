<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Requirement;

use Composer\Composer;
use Composer\Package\Link;
use Composer\Package\PackageInterface;
use Composer\Repository\PlatformRepository;
use Composer\Semver\Constraint\Constraint;
use Composer\Semver\Constraint\ConstraintInterface;
use Composer\Semver\VersionParser;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Composer\Factory;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\Requirement\Exception\ComposerNameMissingException;
use Shopware\Core\Framework\Plugin\Requirement\Exception\ConflictingPackageException;
use Shopware\Core\Framework\Plugin\Requirement\Exception\MissingRequirementException;
use Shopware\Core\Framework\Plugin\Requirement\Exception\RequirementStackException;
use Shopware\Core\Framework\Plugin\Requirement\Exception\VersionMismatchException;
use Shopware\Core\Framework\Plugin\Util\PluginFinder;

#[Package('core')]
class RequirementsValidator
{
    private Composer $pluginComposer;

    private Composer $shopwareProjectComposer;

    /**
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $pluginRepo,
        private readonly string $projectDir
    ) {
    }

    /**
     * @throws RequirementStackException
     */
    public function validateRequirements(PluginEntity $plugin, Context $context, string $method): void
    {
        if ($plugin->getManagedByComposer()) {
            // Composer does the requirements checking if the plugin is managed by composer
            // no need to do it manually

            return;
        }

        $this->shopwareProjectComposer = $this->getComposer($this->projectDir);
        $exceptionStack = new RequirementExceptionStack();

        $pluginDependencies = $this->getPluginDependencies($plugin);

        $pluginDependencies = $this->validateComposerPackages($pluginDependencies, $exceptionStack);
        $pluginDependencies = $this->validateInstalledPlugins($context, $plugin, $pluginDependencies, $exceptionStack);
        $pluginDependencies = $this->validateShippedDependencies($plugin, $pluginDependencies, $exceptionStack);

        $this->addRemainingRequirementsAsException($pluginDependencies['require'], $exceptionStack);

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
        return array_filter($dependants, function (PluginEntity $dependant) use ($dependency) {
            if (!$dependant->getActive()) {
                return false;
            }

            return $this->dependsOn($dependant, $dependency);
        });
    }

    /**
     * dependsOn determines, whether a given plugin depends on another one.
     *
     * @param PluginEntity $plugin     the plugin to be checked
     * @param PluginEntity $dependency the potential dependency
     */
    private function dependsOn(PluginEntity $plugin, PluginEntity $dependency): bool
    {
        if (\in_array($dependency->getComposerName(), array_keys($this->getPluginDependencies($plugin)['require']), true)) {
            return true;
        }

        return false;
    }

    /**
     * @return array{'require': Link[], 'conflict': Link[]}
     */
    private function getPluginDependencies(PluginEntity $plugin): array
    {
        $this->pluginComposer = $this->getComposer($this->projectDir . '/' . $plugin->getPath());
        $package = $this->pluginComposer->getPackage();

        return [
            'require' => $package->getRequires(),
            'conflict' => $package->getConflicts(),
        ];
    }

    /**
     * @param array{'require': Link[], 'conflict': Link[]} $pluginDependencies
     *
     * @return array{'require': Link[], 'conflict': Link[]}
     */
    private function validateComposerPackages(
        array $pluginDependencies,
        RequirementExceptionStack $exceptionStack
    ): array {
        return $this->checkComposerDependencies(
            $pluginDependencies,
            $exceptionStack,
            $this->shopwareProjectComposer
        );
    }

    private function getComposer(string $composerPath): Composer
    {
        return Factory::createComposer($composerPath);
    }

    /**
     * @param array{'require': Link[], 'conflict': Link[]} $pluginDependencies
     *
     * @return array{'require': Link[], 'conflict': Link[]}
     */
    private function checkComposerDependencies(
        array $pluginDependencies,
        RequirementExceptionStack $exceptionStack,
        Composer $composer
    ): array {
        $packages = $composer->getRepositoryManager()->getLocalRepository()->getPackages();

        // Get PHP extension "packages"
        $packages = array_merge(
            $packages,
            (new PlatformRepository())->getPackages(),
        );

        // add root package
        $packages[] = $composer->getPackage();

        foreach ($packages as $package) {
            // Ignore Shopware plugins. They are checked separately in `validateInstalledPlugins`
            if ($package->getType() === PluginFinder::COMPOSER_TYPE) {
                continue;
            }

            $pluginDependencies['require'] = $this->checkRequirement(
                $pluginDependencies['require'],
                $package->getName(),
                new Constraint('==', $package->getVersion()),
                $exceptionStack
            );

            $pluginDependencies['conflict'] = $this->checkConflict(
                $pluginDependencies['conflict'],
                $this->pluginComposer->getPackage()->getName(),
                $package->getName(),
                new Constraint('==', $package->getVersion()),
                $exceptionStack
            );

            $pluginDependencies = $this->validateReplaces($package, $pluginDependencies, $exceptionStack);
        }

        return $pluginDependencies;
    }

    /**
     * @param array{'require': Link[], 'conflict': Link[]} $pluginDependencies
     *
     * @return array{'require': Link[], 'conflict': Link[]}
     */
    private function validateInstalledPlugins(
        Context $context,
        PluginEntity $installingPlugin,
        array $pluginDependencies,
        RequirementExceptionStack $exceptionStack
    ): array {
        $parser = new VersionParser();
        $pluginPackages = $this->getComposerPackagesFromPlugins();

        foreach ($this->getInstalledPlugins($context) as $pluginEntity) {
            $pluginComposerName = $pluginEntity->getComposerName();
            if ($pluginComposerName === null) {
                $exceptionStack->add(new ComposerNameMissingException($pluginEntity->getName()));

                continue;
            }

            $pluginPath = sprintf('%s/%s', $this->projectDir, $pluginEntity->getPath());

            $installedPluginComposerPackage = $pluginPackages[$pluginComposerName] ?? $this->getComposer($pluginPath)->getPackage();

            $pluginDependencies['require'] = $this->checkRequirement(
                $pluginDependencies['require'],
                $pluginComposerName,
                new Constraint('==', $parser->normalize($pluginEntity->getVersion())),
                $exceptionStack
            );

            // Reverse check, if the already installed plugins do conflict with the current
            $this->checkConflict(
                $installedPluginComposerPackage->getConflicts(),
                $installedPluginComposerPackage->getName(),
                $this->pluginComposer->getPackage()->getName(),
                new Constraint('==', $parser->normalize($installingPlugin->getVersion())),
                $exceptionStack
            );

            $pluginDependencies['conflict'] = $this->checkConflict(
                $pluginDependencies['conflict'],
                $this->pluginComposer->getPackage()->getName(),
                $pluginComposerName,
                new Constraint('==', $parser->normalize($pluginEntity->getVersion())),
                $exceptionStack
            );

            $pluginDependencies = $this->validateReplaces($installedPluginComposerPackage, $pluginDependencies, $exceptionStack);
        }

        return $pluginDependencies;
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
     * @return PackageInterface[]
     */
    private function getComposerPackagesFromPlugins(): array
    {
        $packages = $this->shopwareProjectComposer->getRepositoryManager()->getLocalRepository()->getPackages();
        $pluginPackages = array_filter($packages, static fn (PackageInterface $package) => $package->getType() === PluginFinder::COMPOSER_TYPE);

        $pluginPackagesWithNameAsKey = [];
        foreach ($pluginPackages as $pluginPackage) {
            $pluginPackagesWithNameAsKey[$pluginPackage->getName()] = $pluginPackage;
        }

        return $pluginPackagesWithNameAsKey;
    }

    /**
     * @param array{'require': Link[], 'conflict': Link[]} $pluginDependencies
     *
     * @return array{'require': Link[], 'conflict': Link[]}
     */
    private function validateReplaces(
        PackageInterface $package,
        array $pluginDependencies,
        RequirementExceptionStack $exceptionStack
    ): array {
        foreach ($package->getReplaces() as $replace) {
            $replaceConstraint = $replace->getConstraint();

            if ($replace->getPrettyConstraint() === 'self.version') {
                $replaceConstraint = new Constraint('==', $package->getVersion());
            }

            $pluginDependencies['require'] = $this->checkRequirement(
                $pluginDependencies['require'],
                $replace->getTarget(),
                $replaceConstraint,
                $exceptionStack
            );

            $pluginDependencies['conflict'] = $this->checkConflict(
                $pluginDependencies['conflict'],
                $this->pluginComposer->getPackage()->getName(),
                $replace->getTarget(),
                $replaceConstraint,
                $exceptionStack
            );
        }

        return $pluginDependencies;
    }

    /**
     * @param Link[] $pluginRequirements
     *
     * @return Link[]
     */
    private function checkRequirement(
        array $pluginRequirements,
        string $installedName,
        ConstraintInterface $installedVersion,
        RequirementExceptionStack $exceptionStack
    ): array {
        if (!isset($pluginRequirements[$installedName])) {
            return $pluginRequirements;
        }

        $constraint = $pluginRequirements[$installedName]->getConstraint();

        if ($constraint->matches($installedVersion) === false) {
            $exceptionStack->add(
                new VersionMismatchException($installedName, $constraint->getPrettyString(), $installedVersion->getPrettyString())
            );
        }

        unset($pluginRequirements[$installedName]);

        return $pluginRequirements;
    }

    /**
     * @param Link[] $pluginConflicts
     *
     * @return Link[]
     */
    private function checkConflict(
        array $pluginConflicts,
        string $sourceName,
        string $targetName,
        ConstraintInterface $installedVersion,
        RequirementExceptionStack $exceptionStack
    ): array {
        if (!isset($pluginConflicts[$targetName])) {
            return $pluginConflicts;
        }

        $constraint = $pluginConflicts[$targetName]->getConstraint();

        if ($constraint->matches($installedVersion) === true) {
            $exceptionStack->add(
                new ConflictingPackageException($sourceName, $targetName, $installedVersion->getPrettyString())
            );
        }

        unset($pluginConflicts[$targetName]);

        return $pluginConflicts;
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
     * @param array{'require': Link[], 'conflict': Link[]} $pluginDependencies
     *
     * @return array{'require': Link[], 'conflict': Link[]}
     */
    private function validateShippedDependencies(
        PluginEntity $plugin,
        array $pluginDependencies,
        RequirementExceptionStack $exceptionStack
    ): array {
        if ($plugin->getManagedByComposer()) {
            return $pluginDependencies;
        }

        $vendorDir = $this->pluginComposer->getConfig()->get('vendor-dir');
        if (!is_dir($vendorDir)) {
            return $pluginDependencies;
        }
        $pluginDependencies = $this->checkComposerDependencies(
            $pluginDependencies,
            $exceptionStack,
            $this->pluginComposer
        );

        return $pluginDependencies;
    }
}
