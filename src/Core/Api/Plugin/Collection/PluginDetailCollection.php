<?php declare(strict_types=1);

namespace Shopware\Api\Plugin\Collection;

use Shopware\System\Config\Collection\ConfigFormBasicCollection;
use Shopware\Checkout\Payment\Collection\PaymentMethodBasicCollection;
use Shopware\Api\Plugin\Struct\PluginDetailStruct;
use Shopware\Api\Shop\Collection\ShopTemplateBasicCollection;

class PluginDetailCollection extends PluginBasicCollection
{
    /**
     * @var PluginDetailStruct[]
     */
    protected $elements = [];

    public function getConfigFormIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getConfigForms()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function getConfigForms(): ConfigFormBasicCollection
    {
        $collection = new ConfigFormBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getConfigForms()->getElements());
        }

        return $collection;
    }

    public function getPaymentMethodIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getPaymentMethods()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function getPaymentMethods(): PaymentMethodBasicCollection
    {
        $collection = new PaymentMethodBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getPaymentMethods()->getElements());
        }

        return $collection;
    }

    public function getShopTemplateIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getShopTemplates()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function getShopTemplates(): ShopTemplateBasicCollection
    {
        $collection = new ShopTemplateBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getShopTemplates()->getElements());
        }

        return $collection;
    }

    protected function getExpectedClass(): string
    {
        return PluginDetailStruct::class;
    }
}
