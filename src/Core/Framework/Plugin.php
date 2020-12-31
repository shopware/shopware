<?php declare(strict_types=1);

namespace Shopware\Core\Framework;

use Shopware\Core\Framework\Api\Acl\Role\AclRoleEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\Parameter\AdditionalBundleParameters;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\Kernel;
use Symfony\Component\Routing\RouteCollectionBuilder;

abstract class Plugin extends Bundle
{
    /**
     * @var bool
     */
    private $active;

    /**
     * @var string
     */
    private $basePath;

    final public function __construct(bool $active, string $basePath, ?string $projectDir = null)
    {
        $this->active = $active;
        $this->basePath = $basePath;

        if ($projectDir && mb_strpos($this->basePath, '/') !== 0) {
            $this->basePath = $projectDir . '/' . $this->basePath;
        }

        $this->path = $this->computePluginClassPath();
    }

    final public function isActive(): bool
    {
        return $this->active;
    }

    public function install(InstallContext $installContext): void
    {
    }

    public function postInstall(InstallContext $installContext): void
    {
    }

    public function update(UpdateContext $updateContext): void
    {
    }

    public function postUpdate(UpdateContext $updateContext): void
    {
    }

    public function activate(ActivateContext $activateContext): void
    {
    }

    public function deactivate(DeactivateContext $deactivateContext): void
    {
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
    }

    public function configureRoutes(RouteCollectionBuilder $routes, string $environment): void
    {
        if (!$this->isActive()) {
            return;
        }

        parent::configureRoutes($routes, $environment);
    }

    /**
     * @return Bundle[]
     */
    public function getAdditionalBundles(AdditionalBundleParameters $parameters): array
    {
        return [];
    }

    /**
     * By default the container is rebuild during plugin activation and deactivation to allow the plugin to access
     * its own services. If you are absolutely sure you do not require this feature for you plugin you might want
     * to overwrite this method and return false to improve the activation/deactivation of your plugin. This change will
     * only have an affect in the system context (CLI)
     */
    public function rebuildContainer(): bool
    {
        return true;
    }

    /**
     * Some plugins need to provide 3rd party dependencies.
     * If needed, return true and Shopware will execute `composer require` during the plugin installation.
     * When the plugins gets uninstalled, Shopware executes `composer remove`
     */
    public function executeComposerCommands(): bool
    {
        return false;
    }

    final public function removeMigrations(): void
    {
        // namespace should not start with `shopware`
        if (mb_stripos($this->getMigrationNamespace(), 'shopware') === 0) {
            throw new \RuntimeException('Deleting Shopware migrations is not allowed');
        }

        $class = addcslashes($this->getMigrationNamespace(), '\\_%') . '%';
        Kernel::getConnection()->executeUpdate('DELETE FROM migration WHERE class LIKE :class', ['class' => $class]);
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    public function enrichPrivileges(): array
    {
        return [];
    }

    /**
     * @deprecated tag:v6.4.0.0 use enrichPrivileges instead
     */
    final protected function addPrivileges(string $role, array $privileges): void
    {
        /** @var EntityRepositoryInterface $aclRepository */
        $aclRepository = $this->container->get('acl_role.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new ContainsFilter('privileges', $role));
        $roles = $aclRepository->search($criteria, Context::createDefaultContext());

        foreach ($roles as $role) {
            $role->setPrivileges(array_merge($role->getPrivileges(), $privileges));
            $aclRepository->update(
                [
                    [
                        'id' => $role->getId(),
                        'privileges' => $role->getPrivileges(),
                    ],
                ],
                Context::createDefaultContext()
            );
        }
    }

    /**
     * @deprecated tag:v6.4.0.0 will be removed
     */
    final protected function removePrivileges(array $privileges): void
    {
        /** @var EntityRepositoryInterface $aclRepository */
        $aclRepository = $this->container->get('acl_role.repository');

        foreach ($privileges as $privilege) {
            $criteria = new Criteria();
            $criteria->addFilter(new ContainsFilter('privileges', $privilege));
            $roles = $aclRepository->search($criteria, Context::createDefaultContext());

            /** @var AclRoleEntity $role */
            foreach ($roles as $role) {
                $role->setPrivileges(array_diff($role->getPrivileges(), [$privilege]));
                $aclRepository->update(
                    [
                        [
                            'id' => $role->getId(),
                            'privileges' => $role->getPrivileges(),
                        ],
                    ],
                    Context::createDefaultContext()
                );
            }
        }
    }

    private function computePluginClassPath(): string
    {
        $canonicalizedPluginClassPath = parent::getPath();
        $canonicalizedPluginPath = realpath($this->basePath);

        if ($canonicalizedPluginPath !== false && mb_strpos($canonicalizedPluginClassPath, $canonicalizedPluginPath) === 0) {
            $relativePluginClassPath = mb_substr($canonicalizedPluginClassPath, mb_strlen($canonicalizedPluginPath));

            return $this->basePath . $relativePluginClassPath;
        }

        return parent::getPath();
    }
}
