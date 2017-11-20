<?php declare(strict_types=1);

namespace Shopware\Plugin\Event\Plugin;

use Shopware\Config\Event\ConfigForm\ConfigFormBasicLoadedEvent;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Payment\Event\PaymentMethod\PaymentMethodBasicLoadedEvent;
use Shopware\Plugin\Collection\PluginDetailCollection;
use Shopware\Shop\Event\ShopTemplate\ShopTemplateBasicLoadedEvent;

class PluginDetailLoadedEvent extends NestedEvent
{
    const NAME = 'plugin.detail.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var PluginDetailCollection
     */
    protected $plugins;

    public function __construct(PluginDetailCollection $plugins, TranslationContext $context)
    {
        $this->context = $context;
        $this->plugins = $plugins;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
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
