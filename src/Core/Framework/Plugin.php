<?php declare(strict_types=1);

namespace Shopware\Core\Framework;

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
