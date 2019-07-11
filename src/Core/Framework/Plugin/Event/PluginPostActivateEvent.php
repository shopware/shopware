<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Event;

use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\PluginEntity;

class PluginPostActivateEvent extends PluginLifecycleEvent
{
    /**
     * @var ActivateContext
     */
    private $context;

    public function __construct(PluginEntity $plugin, ActivateContext $context)
    {
        parent::__construct($plugin);
        $this->context = $context;
    }

    public function getContext(): ActivateContext
    {
        return $this->context;
    }
}
