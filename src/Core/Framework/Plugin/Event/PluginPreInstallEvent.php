<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Event;

use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\PluginEntity;

class PluginPreInstallEvent extends PluginLifecycleEvent
{
    /**
     * @var InstallContext
     */
    private $context;

    public function __construct(PluginEntity $plugin, InstallContext $context)
    {
        parent::__construct($plugin);
        $this->context = $context;
    }

    public function getContext(): InstallContext
    {
        return $this->context;
    }
}
