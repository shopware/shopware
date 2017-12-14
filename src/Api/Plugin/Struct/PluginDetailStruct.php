<?php declare(strict_types=1);

namespace Shopware\Api\Plugin\Struct;

use Shopware\Api\Config\Collection\ConfigFormBasicCollection;
use Shopware\Api\Payment\Collection\PaymentMethodBasicCollection;
use Shopware\Api\Shop\Collection\ShopTemplateBasicCollection;

class PluginDetailStruct extends PluginBasicStruct
{
    /**
     * @var ConfigFormBasicCollection
     */
    protected $configForms;

    /**
     * @var PaymentMethodBasicCollection
     */
    protected $paymentMethods;

    /**
     * @var ShopTemplateBasicCollection
     */
    protected $shopTemplates;

    public function __construct()
    {
        $this->configForms = new ConfigFormBasicCollection();

        $this->paymentMethods = new PaymentMethodBasicCollection();

        $this->shopTemplates = new ShopTemplateBasicCollection();
    }

    public function getConfigForms(): ConfigFormBasicCollection
    {
        return $this->configForms;
    }

    public function setConfigForms(ConfigFormBasicCollection $configForms): void
    {
        $this->configForms = $configForms;
    }

    public function getPaymentMethods(): PaymentMethodBasicCollection
    {
        return $this->paymentMethods;
    }

    public function setPaymentMethods(PaymentMethodBasicCollection $paymentMethods): void
    {
        $this->paymentMethods = $paymentMethods;
    }

    public function getShopTemplates(): ShopTemplateBasicCollection
    {
        return $this->shopTemplates;
    }

    public function setShopTemplates(ShopTemplateBasicCollection $shopTemplates): void
    {
        $this->shopTemplates = $shopTemplates;
    }
}
