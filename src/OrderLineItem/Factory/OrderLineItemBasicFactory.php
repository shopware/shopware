<?php declare(strict_types=1);

namespace Shopware\OrderLineItem\Factory;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\ExtensionRegistryInterface;
use Shopware\Framework\Factory\Factory;
use Shopware\OrderLineItem\Extension\OrderLineItemExtension;
use Shopware\OrderLineItem\Struct\OrderLineItemBasicStruct;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;

class OrderLineItemBasicFactory extends Factory
{
    const ROOT_NAME = 'order_line_item';
    const EXTENSION_NAMESPACE = 'orderLineItem';

    const FIELDS = [
       'uuid' => 'uuid',
       'order_uuid' => 'order_uuid',
       'identifier' => 'identifier',
       'quantity' => 'quantity',
       'unit_price' => 'unit_price',
       'total_price' => 'total_price',
       'type' => 'type',
       'payload' => 'payload',
       'created_at' => 'created_at',
       'updated_at' => 'updated_at',
    ];

    public function __construct(
        Connection $connection,
        ExtensionRegistryInterface $registry
    ) {
        parent::__construct($connection, $registry);
    }

    public function hydrate(
        array $data,
        OrderLineItemBasicStruct $orderLineItem,
        QuerySelection $selection,
        TranslationContext $context
    ): OrderLineItemBasicStruct {
        $orderLineItem->setUuid((string) $data[$selection->getField('uuid')]);
        $orderLineItem->setOrderUuid((string) $data[$selection->getField('order_uuid')]);
        $orderLineItem->setIdentifier((string) $data[$selection->getField('identifier')]);
        $orderLineItem->setQuantity((int) $data[$selection->getField('quantity')]);
        $orderLineItem->setUnitPrice((float) $data[$selection->getField('unit_price')]);
        $orderLineItem->setTotalPrice((float) $data[$selection->getField('total_price')]);
        $orderLineItem->setType(isset($data[$selection->getField('type')]) ? (string) $data[$selection->getField('type')] : null);
        $orderLineItem->setPayload((string) $data[$selection->getField('payload')]);
        $orderLineItem->setCreatedAt(isset($data[$selection->getField('created_at')]) ? new \DateTime($data[$selection->getField('created_at')]) : null);
        $orderLineItem->setUpdatedAt(isset($data[$selection->getField('updated_at')]) ? new \DateTime($data[$selection->getField('updated_at')]) : null);

        /** @var $extension OrderLineItemExtension */
        foreach ($this->getExtensions() as $extension) {
            $extension->hydrate($orderLineItem, $data, $selection, $context);
        }

        return $orderLineItem;
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
                'order_line_item_translation',
                $translation->getRootEscaped(),
                sprintf(
                    '%s.order_line_item_uuid = %s.uuid AND %s.language_uuid = :languageUuid',
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
