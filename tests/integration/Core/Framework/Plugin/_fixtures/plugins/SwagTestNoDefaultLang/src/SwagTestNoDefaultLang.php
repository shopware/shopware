<?php declare(strict_types=1);

namespace SwagTestNoDefaultLang;

use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;

class SwagTestNoDefaultLang extends Plugin
{
    final public const PLUGIN_LABEL = 'Dutch Pluginname';

    final public const PLUGIN_VERSION = '1.0.1';

    final public const PLUGIN_OLD_VERSION = '1.0.0';

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);
    }

    public function update(UpdateContext $updateContext): void
    {
        parent::update($updateContext);
    }

    public function deactivate(DeactivateContext $deactivateContext): void
    {
        parent::deactivate($deactivateContext);
    }
}
