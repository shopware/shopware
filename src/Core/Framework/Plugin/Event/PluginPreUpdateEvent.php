<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Event;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\Framework\Plugin\PluginEntity;

#[Package('core')]
class PluginPreUpdateEvent extends PluginLifecycleEvent
{
    public function __construct(
        PluginEntity $plugin,
        private readonly UpdateContext $context
    ) {
        parent::__construct($plugin);
    }

    public function getContext(): UpdateContext
    {
        return $this->context;
    }
}
