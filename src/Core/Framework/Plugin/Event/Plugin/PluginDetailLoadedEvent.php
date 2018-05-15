<?php declare(strict_types=1);

namespace Shopware\Framework\Plugin\Event\Plugin;

use Shopware\System\Config\Event\ConfigForm\ConfigFormBasicLoadedEvent;
use Shopware\Checkout\Payment\Event\PaymentMethod\PaymentMethodBasicLoadedEvent;
use Shopware\Framework\Plugin\Collection\PluginDetailCollection;
use Shopware\Api\Shop\Event\ShopTemplate\ShopTemplateBasicLoadedEvent;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class PluginDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'plugin.detail.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var PluginDetailCollection
     */
    protected $plugins;

    public function __construct(PluginDetailCollection $plugins, ApplicationContext $context)
    {
        $this->context = $context;
        $this->plugins = $plugins;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
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
        if ($this->plugins->getShopTemplates()->count() > 0) {
            $events[] = new ShopTemplateBasicLoadedEvent($this->plugins->getShopTemplates(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
