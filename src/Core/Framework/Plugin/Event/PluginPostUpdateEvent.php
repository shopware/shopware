<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Event;

use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\Framework\Plugin\PluginEntity;

class PluginPostUpdateEvent extends PluginLifecycleEvent
{
    /**
     * @var UpdateContext
     */
    private $context;

    public function __construct(PluginEntity $plugin, UpdateContext $context)
    {
        parent::__construct($plugin);
        $this->context = $context;
    }

    public function getContext(): UpdateContext
    {
        return $this->context;
    }
}
