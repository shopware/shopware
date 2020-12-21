<?php declare(strict_types=1);

namespace SwagTestPluginAcl;

use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;

/** @deprecated tag:v6.4.0.0 */
class SwagTestPluginAcl extends Plugin
{
    public function activate(ActivateContext $context): void
    {
        $this->addPrivileges('product.viewer', ['swag_demo_data:read']);
    }

    public function deactivate(DeactivateContext $context): void
    {
        $this->removePrivileges(['swag_demo_data:read']);
    }
}
