<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityEntity;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Uuid\Uuid;

class ProductSerializer extends EntitySerializer
{
    public const VISIBILITY_MAPPING = [
        ProductVisibilityDefinition::VISIBILITY_ALL => 'all',
        ProductVisibilityDefinition::VISIBILITY_LINK => 'link',
        ProductVisibilityDefinition::VISIBILITY_SEARCH => 'search',
    ];

    private EntityRepositoryInterface $visibilityRepository;

    private EntityRepositoryInterface $salesChannelRepository;

    private EntityRepositoryInterface $productMediaRepository;

    private EntityRepositoryInterface $productConfiguratorSettingRepository;

    public function __construct(
        EntityRepositoryInterface $visibilityRepository,
        EntityRepositoryInterface $salesChannelRepository,
        EntityRepositoryInterface $productMediaRepository,
        EntityRepositoryInterface $productConfiguratorSettingRepository
    ) {
        $this->visibilityRepository = $visibilityRepository;
        $this->salesChannelRepository = $salesChannelRepository;
        $this->productMediaRepository = $productMediaRepository;
        $this->productConfiguratorSettingRepository = $productConfiguratorSettingRepository;
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

        if (!isset($entity['visibilities'])) {
            return;
        }

        $visibilities = $entity['visibilities'];
        if ($visibilities instanceof Struct) {
            $visibilities = $visibilities->jsonSerialize();
        }

        $groups = [];
        foreach ($visibilities as $visibility) {
            $visibility = $visibility instanceof ProductVisibilityEntity
                ? $visibility->jsonSerialize()
                : $visibility;
            $groups[$visibility['visibility']] = $groups[$visibility['visibility']] ?? [];
            $groups[$visibility['visibility']][] = $visibility['salesChannelId'];
        }

        $result = [];

        foreach (self::VISIBILITY_MAPPING as $type => $key) {
            if (isset($groups[$type])) {
                $result[$key] = implode('|', $groups[$type]);
            }
        }

        if ($result !== []) {
            yield 'visibilities' => $result;
        }
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
        yield from $deserialized;

        $productId = $entity['id'] ?? null;

        $mapping = array_flip(self::VISIBILITY_MAPPING);

        $visibilities = [];

        foreach ($mapping as $key => $type) {
            if (!isset($entity['visibilities'][$key])) {
                continue;
            }

            $ids = array_filter(explode('|', $entity['visibilities'][$key]));

            $ids = $this->convertSalesChannelNamesToIds($ids);

            foreach ($ids as $salesChannelId) {
                $visibility = [
                    'salesChannelId' => $salesChannelId,
                    'visibility' => $type,
                ];
                if ($productId) {
                    $visibility['productId'] = $productId;
                }

                $visibilities[] = $visibility;
            }
        }

        if ($visibilities !== []) {
            yield 'visibilities' => $this->findVisibilityIds($visibilities);
        }

        if (isset($deserialized['id'], $deserialized['cover']['media']['id'])) {
            yield 'cover' => $this->findCoverProductMediaId($deserialized['id'], $deserialized['cover']);
        }

        if (!empty($deserialized['parentId']) && !empty($deserialized['options'])) {
            yield 'configuratorSettings' => $this->findConfiguratorSettings($deserialized['parentId'], $deserialized['options']);
        }
    }

    public function supports(string $entity): bool
    {
        return $entity === ProductDefinition::ENTITY_NAME;
    }

    private function findVisibilityIds(array $visibilities): array
    {
        foreach ($visibilities as $i => $visibility) {
            if (!isset($visibility['productId'])) {
                continue;
            }

            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('productId', $visibility['productId']));
            $criteria->addFilter(new EqualsFilter('salesChannelId', $visibility['salesChannelId']));

            $id = $this->visibilityRepository->searchIds($criteria, Context::createDefaultContext())->firstId();

            if ($id) {
                $visibility['id'] = $id;
            }

            $visibilities[$i] = $visibility;
        }

        return $visibilities;
    }

    private function convertSalesChannelNamesToIds(array $ids): array
    {
        $salesChannelNames = [];

        foreach ($ids as $key => $id) {
            if (!Uuid::isValid($id)) {
                $salesChannelNames[] = $id;
                unset($ids[$key]);
            }
        }

        if (empty($salesChannelNames)) {
            return $ids;
        }

        $salesChannelNames = array_unique($salesChannelNames);
        $filters = [];

        foreach ($salesChannelNames as $salesChannelName) {
            $filters[] = new EqualsFilter('name', $salesChannelName);
        }

        $criteria = new Criteria();
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, $filters));

        $additionalIds = $this->salesChannelRepository->searchIds(
            $criteria,
            Context::createDefaultContext()
        )->getIds();

        return array_unique(array_merge($ids, $additionalIds));
    }

    private function findCoverProductMediaId(string $productId, array $cover): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('productId', $productId));
        $criteria->addFilter(new EqualsFilter('mediaId', $cover['media']['id']));

        $id = $this->productMediaRepository->searchIds($criteria, Context::createDefaultContext())->firstId();

        if ($id) {
            $cover['id'] = $id;
        }

        return $cover;
    }

    private function findConfiguratorSettings(string $parentId, array $options): array
    {
        $configuratorSettings = [];

        foreach ($options as $option) {
            if (empty($option['id'])) {
                continue;
            }

            $configuratorSetting = [
                'optionId' => $option['id'],
                'product' => [
                    'id' => $parentId,
                ],
            ];

            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('productId', $parentId));
            $criteria->addFilter(new EqualsFilter('optionId', $option['id']));

            $id = $this->productConfiguratorSettingRepository->searchIds($criteria, Context::createDefaultContext())->firstId();

            if ($id) {
                $configuratorSetting['id'] = $id;
            }

            $configuratorSettings[] = $configuratorSetting;
        }

        return $configuratorSettings;
    }
}
