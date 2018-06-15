<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Event;

use Shopware\Core\Checkout\Payment\Event\PaymentMethodBasicLoadedEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Plugin\Collection\PluginDetailCollection;
use Shopware\Core\System\Config\Event\ConfigFormBasicLoadedEvent;

class PluginDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'plugin.detail.loaded';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var PluginDetailCollection
     */
    protected $plugins;

    public function __construct(PluginDetailCollection $plugins, Context $context)
    {
        $this->context = $context;
        $this->plugins = $plugins;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getPlugins(): PluginDetailCollection
    {
        return $this->plugins;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->plugins->getConfigForms()->count() > 0) {
            $events[] = new ConfigFormBasicLoadedEvent($this->plugins->getConfigForms(), $this->context);
        }
        if ($this->plugins->getPaymentMethods()->count() > 0) {
            $events[] = new PaymentMethodBasicLoadedEvent($this->plugins->getPaymentMethods(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
