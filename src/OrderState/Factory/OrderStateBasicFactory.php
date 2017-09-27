<?php declare(strict_types=1);

namespace Shopware\OrderState\Factory;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\ExtensionRegistryInterface;
use Shopware\Framework\Factory\Factory;
use Shopware\OrderState\Extension\OrderStateExtension;
use Shopware\OrderState\Struct\OrderStateBasicStruct;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;

class OrderStateBasicFactory extends Factory
{
    const ROOT_NAME = 'order_state';
    const EXTENSION_NAMESPACE = 'orderState';

    const FIELDS = [
       'uuid' => 'uuid',
       'name' => 'name',
       'position' => 'position',
       'type' => 'type',
       'has_mail' => 'has_mail',
       'created_at' => 'created_at',
       'updated_at' => 'updated_at',
       'description' => 'translation.description',
    ];

    public function __construct(
        Connection $connection,
        ExtensionRegistryInterface $registry
    ) {
        parent::__construct($connection, $registry);
    }

    public function hydrate(
        array $data,
        OrderStateBasicStruct $orderState,
        QuerySelection $selection,
        TranslationContext $context
    ): OrderStateBasicStruct {
        $orderState->setUuid((string) $data[$selection->getField('uuid')]);
        $orderState->setName((string) $data[$selection->getField('name')]);
        $orderState->setPosition((int) $data[$selection->getField('position')]);
        $orderState->setType((string) $data[$selection->getField('type')]);
        $orderState->setHasMail((bool) $data[$selection->getField('has_mail')]);
        $orderState->setCreatedAt(isset($data[$selection->getField('created_at')]) ? new \DateTime($data[$selection->getField('created_at')]) : null);
        $orderState->setUpdatedAt(isset($data[$selection->getField('updated_at')]) ? new \DateTime($data[$selection->getField('updated_at')]) : null);
        $orderState->setDescription((string) $data[$selection->getField('description')]);

        /** @var $extension OrderStateExtension */
        foreach ($this->getExtensions() as $extension) {
            $extension->hydrate($orderState, $data, $selection, $context);
        }

        return $orderState;
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
                'order_state_translation',
                $translation->getRootEscaped(),
                sprintf(
                    '%s.order_state_uuid = %s.uuid AND %s.language_uuid = :languageUuid',
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
