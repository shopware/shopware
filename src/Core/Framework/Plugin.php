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
     * @var bool
     */
    private $active;

    public function __construct(bool $active = true, ?string $path = null)
    {
        $this->active = $active;
        $this->path = $path;
    }

    public function isActive(): bool
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
}
