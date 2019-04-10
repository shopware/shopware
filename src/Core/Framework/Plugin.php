<?php declare(strict_types=1);

namespace Shopware\Core\Framework;

use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Symfony\Component\Routing\RouteCollectionBuilder;

abstract class Plugin extends Bundle
{
    /**
     * @var string
     */
    protected $pluginPath;
    /**
     * @var bool
     */
    private $active;

    final public function __construct(bool $active = true, ?string $pluginPath = null)
    {
        $this->active = $active;
        $this->pluginPath = $pluginPath;
        $this->path = $this->computePluginClassPath($pluginPath);
    }

    final public function isActive(): bool
    {
        return $this->active;
    }

    public function install(InstallContext $context): void
    {
    }

    public function postInstall(InstallContext $context): void
    {
    }

    public function update(UpdateContext $context): void
    {
    }

    public function postUpdate(UpdateContext $context): void
    {
    }

    public function activate(ActivateContext $context): void
    {
    }

    public function deactivate(DeactivateContext $context): void
    {
    }

    public function uninstall(UninstallContext $context): void
    {
    }

    public function configureRoutes(RouteCollectionBuilder $routes, string $environment): void
    {
        if (!$this->isActive()) {
            return;
        }

        parent::configureRoutes($routes, $environment);
    }

    public function getPluginPath(): string
    {
        return $this->pluginPath;
    }

    private function computePluginClassPath(string $pluginPath)
    {
        $canonicalizedPluginClassPath = parent::getPath();
        $canonicalizedPluginPath = realpath($pluginPath);

        if (mb_strpos($canonicalizedPluginClassPath, $canonicalizedPluginPath) === 0) {
            $relativePluginClassPath = mb_substr($canonicalizedPluginClassPath, mb_strlen($canonicalizedPluginPath));

            return $pluginPath . $relativePluginClassPath;
        }

        return parent::getPath();
    }
}
