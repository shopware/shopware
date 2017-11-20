<?php declare(strict_types=1);

namespace Shopware\Plugin\Collection;

use Shopware\Config\Collection\ConfigFormBasicCollection;
use Shopware\Payment\Collection\PaymentMethodBasicCollection;
use Shopware\Plugin\Struct\PluginDetailStruct;
use Shopware\Shop\Collection\ShopTemplateBasicCollection;

class PluginDetailCollection extends PluginBasicCollection
{
    /**
     * @var PluginDetailStruct[]
     */
    protected $elements = [];

    public function getConfigFormUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getConfigForms()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getConfigForms(): ConfigFormBasicCollection
    {
        $collection = new ConfigFormBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getConfigForms()->getElements());
        }

        return $collection;
    }

    public function getPaymentMethodUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getPaymentMethods()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getPaymentMethods(): PaymentMethodBasicCollection
    {
        $collection = new PaymentMethodBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getPaymentMethods()->getElements());
        }

        return $collection;
    }

    public function getShopTemplateUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getShopTemplates()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
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
