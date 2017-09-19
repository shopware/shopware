<?php

namespace Shopware\ShippingMethodPrice\Factory;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\Factory;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Shopware\ShippingMethodPrice\Struct\ShippingMethodPriceBasicStruct;

class ShippingMethodPriceBasicFactory extends Factory
{
    const ROOT_NAME = 'shipping_method_price';
    const EXTENSION_NAMESPACE = 'shippingMethodPrice';

    const FIELDS = [
       'uuid' => 'uuid',
       'shipping_method_uuid' => 'shipping_method_uuid',
       'quantity_from' => 'quantity_from',
       'price' => 'price',
       'factor' => 'factor',
    ];

    public function hydrate(
        array $data,
        ShippingMethodPriceBasicStruct $shippingMethodPrice,
        QuerySelection $selection,
        TranslationContext $context
    ): ShippingMethodPriceBasicStruct {
        $shippingMethodPrice->setUuid((string) $data[$selection->getField('uuid')]);
        $shippingMethodPrice->setShippingMethodUuid((string) $data[$selection->getField('shipping_method_uuid')]);
        $shippingMethodPrice->setQuantityFrom((float) $data[$selection->getField('quantity_from')]);
        $shippingMethodPrice->setPrice((float) $data[$selection->getField('price')]);
        $shippingMethodPrice->setFactor((float) $data[$selection->getField('factor')]);

        foreach ($this->getExtensions() as $extension) {
            $extension->hydrate($shippingMethodPrice, $data, $selection, $context);
        }

        return $shippingMethodPrice;
    }

    public function getFields(): array
    {
        $fields = array_merge(self::FIELDS, parent::getFields());

        return $fields;
    }

    public function joinDependencies(QuerySelection $selection, QueryBuilder $query, TranslationContext $context): void
    {
        if ($translation = $selection->filter('translation')) {
            $query->leftJoin(
                $selection->getRootEscaped(),
                'shipping_method_price_translation',
                $translation->getRootEscaped(),
                sprintf(
                    '%s.shipping_method_price_uuid = %s.uuid AND %s.language_uuid = :languageUuid',
                    $translation->getRootEscaped(),
                    $selection->getRootEscaped(),
                    $translation->getRootEscaped()
                )
            );
            $query->setParameter('languageUuid', $context->getShopUuid());
        }

        $this->joinExtensionDependencies($selection, $query, $context);
    }

    public function getAllFields(): array
    {
        $fields = array_merge(self::FIELDS, $this->getExtensionFields());

        return $fields;
    }

    protected function getRootName(): string
    {
        return self::ROOT_NAME;
    }

    protected function getExtensionNamespace(): string
    {
        return self::EXTENSION_NAMESPACE;
    }
}
