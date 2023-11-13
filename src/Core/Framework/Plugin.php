<?php declare(strict_types=1);

namespace Shopware\Core\Framework;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Parameter\AdditionalBundleParameters;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

#[Package('core')]
abstract class Plugin extends Bundle
{
    /**
     * @internal
     */
    final public function __construct(
        private readonly bool $active,
        private string $basePath,
        ?string $projectDir = null
    ) {
        if ($projectDir && mb_strpos($this->basePath, '/') !== 0) {
            $this->basePath = rtrim($projectDir, '/') . '/' . $this->basePath;
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

    public function configureRoutes(RoutingConfigurator $routes, string $environment): void
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
        if (str_starts_with(mb_strtolower($this->getMigrationNamespace()), 'shopware') && !str_starts_with(mb_strtolower($this->getMigrationNamespace()), 'shopware\commercial')) {
            throw new \RuntimeException('Deleting Shopware migrations is not allowed');
        }

        $class = addcslashes($this->getMigrationNamespace(), '\\_%') . '%';
        Kernel::getConnection()->executeStatement('DELETE FROM migration WHERE class LIKE :class', ['class' => $class]);
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
     * Used to configure the BaseUrl for the Admin Extension API
     */
    public function getAdminBaseUrl(): ?string
    {
        return null;
    }

    private function computePluginClassPath(): string
    {
        $canonicalizedPluginClassPath = $this->getPath();
        $canonicalizedPluginPath = realpath($this->basePath);

        if ($canonicalizedPluginPath !== false && mb_strpos($canonicalizedPluginClassPath, $canonicalizedPluginPath) === 0) {
            $relativePluginClassPath = mb_substr($canonicalizedPluginClassPath, mb_strlen($canonicalizedPluginPath));

            return $this->basePath . $relativePluginClassPath;
        }

        return $this->getPath();
    }
}
