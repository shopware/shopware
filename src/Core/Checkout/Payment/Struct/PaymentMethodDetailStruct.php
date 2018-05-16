<?php declare(strict_types=1);

namespace Shopware\Checkout\Payment\Struct;

use Shopware\Checkout\Payment\Aggregate\PaymentMethodTranslation\Collection\PaymentMethodTranslationBasicCollection;
use Shopware\Framework\Plugin\Struct\PluginBasicStruct;

class PaymentMethodDetailStruct extends PaymentMethodBasicStruct
{
    /**
     * @var PluginBasicStruct|null
     */
    protected $plugin;

    /**
     * @var \Shopware\Checkout\Payment\Aggregate\PaymentMethodTranslation\Collection\PaymentMethodTranslationBasicCollection
     */
    protected $translations;

    public function __construct()
    {
        $this->translations = new PaymentMethodTranslationBasicCollection();
    }

    public function getPlugin(): ?PluginBasicStruct
    {
        return $this->plugin;
    }

    public function setPlugin(?PluginBasicStruct $plugin): void
    {
        $this->plugin = $plugin;
    }

    public function getTranslations(): PaymentMethodTranslationBasicCollection
    {
        return $this->translations;
    }

    public function setTranslations(PaymentMethodTranslationBasicCollection $translations): void
    {
        $this->translations = $translations;
    }
}
