<?php declare(strict_types=1);

namespace Shopware\OrderState\Factory;

use Doctrine\DBAL\Connection;
use Shopware\Api\Read\ExtensionRegistryInterface;
use Shopware\Api\Read\Factory;
use Shopware\Api\Search\QueryBuilder;
use Shopware\Api\Search\QuerySelection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\OrderState\Extension\OrderStateExtension;
use Shopware\OrderState\Struct\OrderStateBasicStruct;

class OrderStateBasicFactory extends Factory
{
    const ROOT_NAME = 'order_state';
    const EXTENSION_NAMESPACE = 'orderState';

    const FIELDS = [
       'uuid' => 'uuid',
       'name' => 'name',
       'position' => 'position',
       'type' => 'type',
       'hasMail' => 'has_mail',
       'createdAt' => 'created_at',
       'updatedAt' => 'updated_at',
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
        $orderState->setHasMail((bool) $data[$selection->getField('hasMail')]);
        $orderState->setCreatedAt(isset($data[$selection->getField('createdAt')]) ? new \DateTime($data[$selection->getField('createdAt')]) : null);
        $orderState->setUpdatedAt(isset($data[$selection->getField('updatedAt')]) ? new \DateTime($data[$selection->getField('updatedAt')]) : null);
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
}
