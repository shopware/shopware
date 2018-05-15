<?php declare(strict_types=1);

namespace Shopware\Checkout\Payment\Collection;

use Shopware\Checkout\Payment\Struct\PaymentMethodDetailStruct;
use Shopware\Api\Plugin\Collection\PluginBasicCollection;

class PaymentMethodDetailCollection extends PaymentMethodBasicCollection
{
    /**
     * @var PaymentMethodDetailStruct[]
     */
    protected $elements = [];

    public function getPlugins(): PluginBasicCollection
    {
        return new PluginBasicCollection(
            $this->fmap(function (PaymentMethodDetailStruct $paymentMethod) {
                return $paymentMethod->getPlugin();
            })
        );
    }

    public function getTranslationIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getTranslations()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function getTranslations(): PaymentMethodTranslationBasicCollection
    {
        $collection = new PaymentMethodTranslationBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getTranslations()->getElements());
        }

        return $collection;
    }

    protected function getExpectedClass(): string
    {
        return PaymentMethodDetailStruct::class;
    }
}
