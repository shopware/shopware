<?php declare(strict_types=1);

namespace Shopware\Api\Payment\Struct;

use Shopware\Api\Payment\Collection\PaymentMethodTranslationBasicCollection;
use Shopware\Api\Plugin\Struct\PluginBasicStruct;

class PaymentMethodDetailStruct extends PaymentMethodBasicStruct
{
    /**
     * @var PluginBasicStruct|null
     */
    protected $plugin;

    /**
     * @var PaymentMethodTranslationBasicCollection
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
