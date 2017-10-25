<?php declare(strict_types=1);

namespace Shopware\OrderLineItem\Factory;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Read\ExtensionRegistryInterface;
use Shopware\Framework\Read\Factory;
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
       'orderUuid' => 'order_uuid',
       'identifier' => 'identifier',
       'quantity' => 'quantity',
       'unitPrice' => 'unit_price',
       'totalPrice' => 'total_price',
       'type' => 'type',
       'payload' => 'payload',
       'createdAt' => 'created_at',
       'updatedAt' => 'updated_at',
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
        $orderLineItem->setOrderUuid((string) $data[$selection->getField('orderUuid')]);
        $orderLineItem->setIdentifier((string) $data[$selection->getField('identifier')]);
        $orderLineItem->setQuantity((int) $data[$selection->getField('quantity')]);
        $orderLineItem->setUnitPrice((float) $data[$selection->getField('unitPrice')]);
        $orderLineItem->setTotalPrice((float) $data[$selection->getField('totalPrice')]);
        $orderLineItem->setType(isset($data[$selection->getField('type')]) ? (string) $data[$selection->getField('type')] : null);
        $orderLineItem->setPayload((string) $data[$selection->getField('payload')]);
        $orderLineItem->setCreatedAt(isset($data[$selection->getField('createdAt')]) ? new \DateTime($data[$selection->getField('createdAt')]) : null);
        $orderLineItem->setUpdatedAt(isset($data[$selection->getField('updatedAt')]) ? new \DateTime($data[$selection->getField('updatedAt')]) : null);

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
        $this->joinTranslation($selection, $query, $context);

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

    private function joinTranslation(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void {
        if (!($translation = $selection->filter('translation'))) {
            return;
        }
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
}
