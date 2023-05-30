<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSellingAssignedProducts\ProductCrossSellingAssignedProductsEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('core')]
class ProductCrossSellingSerializer extends EntitySerializer
{
    /**
     * @internal
     */
    public function __construct(private readonly EntityRepository $assignedProductsRepository)
    {
    }

    /**
     * @param array|Struct|null $entity
     *
     * @return \Generator
     */
    public function serialize(Config $config, EntityDefinition $definition, $entity): iterable
    {
        if ($entity instanceof Struct) {
            $entity = $entity->jsonSerialize();
        }

        yield from parent::serialize($config, $definition, $entity);

        if (!isset($entity['assignedProducts'])) {
            return;
        }

        $assignedProducts = $entity['assignedProducts'];
        if ($assignedProducts instanceof Struct) {
            $assignedProducts = $assignedProducts->jsonSerialize();
        }

        $productIds = [];

        foreach ($assignedProducts as $assignedProduct) {
            $assignedProduct = $assignedProduct instanceof ProductCrossSellingAssignedProductsEntity
                ? $assignedProduct->jsonSerialize()
                : $assignedProduct;
            $productIds[$assignedProduct['position']] = $assignedProduct['productId'];
        }

        ksort($productIds);

        $result = implode('|', $productIds);

        yield 'assignedProducts' => $result;
    }

    /**
     * @param array|\Traversable $entity
     *
     * @return array|\Traversable
     */
    public function deserialize(Config $config, EntityDefinition $definition, $entity)
    {
        $entity = \is_array($entity) ? $entity : iterator_to_array($entity);

        $deserialized = parent::deserialize($config, $definition, $entity);
        $deserialized = \is_array($deserialized) ? $deserialized : iterator_to_array($deserialized);

        if (empty($deserialized['assignedProducts'])) {
            return $deserialized;
        }

        $crossSellingId = $deserialized['id'] ?? null;
        $assignedProducts = [];

        foreach ($deserialized['assignedProducts'] as $position => $productId) {
            $assignedProduct = [
                'productId' => $productId,
                'crossSellingId' => $crossSellingId,
                'position' => $position,
            ];

            $assignedProducts[] = $assignedProduct;
        }

        if ($crossSellingId) {
            $assignedProducts = $this->findAssignedProductsIds($assignedProducts);
        }

        $deserialized['assignedProducts'] = $assignedProducts;

        return $deserialized;
    }

    public function supports(string $entity): bool
    {
        return $entity === ProductCrossSellingDefinition::ENTITY_NAME;
    }

    private function findAssignedProductsIds(array $assignedProducts): array
    {
        foreach ($assignedProducts as $i => $assignedProduct) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('crossSellingId', $assignedProduct['crossSellingId']));
            $criteria->addFilter(new EqualsFilter('productId', $assignedProduct['productId']));

            $id = $this->assignedProductsRepository->searchIds($criteria, Context::createDefaultContext())->firstId();

            if ($id) {
                $assignedProduct['id'] = $id;
            }

            $assignedProducts[$i] = $assignedProduct;
        }

        return $assignedProducts;
    }
}
