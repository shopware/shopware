<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductConfiguratorSetting\ProductConfiguratorSettingCollection;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaCollection;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityCollection;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityEntity;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;

#[Package('fundamentals@after-sales')]
class ProductSerializer extends EntitySerializer
{
    final public const VISIBILITY_MAPPING = [
        ProductVisibilityDefinition::VISIBILITY_ALL => 'all',
        ProductVisibilityDefinition::VISIBILITY_LINK => 'link',
        ProductVisibilityDefinition::VISIBILITY_SEARCH => 'search',
    ];

    /**
     * @internal
     *
     * @param EntityRepository<ProductVisibilityCollection> $visibilityRepository
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     * @param EntityRepository<ProductMediaCollection> $productMediaRepository
     * @param EntityRepository<ProductConfiguratorSettingCollection> $productConfiguratorSettingRepository
     */
    public function __construct(
        private readonly EntityRepository $visibilityRepository,
        private readonly EntityRepository $salesChannelRepository,
        private readonly EntityRepository $productMediaRepository,
        private readonly EntityRepository $productConfiguratorSettingRepository
    ) {
    }

    public function serialize(Config $config, EntityDefinition $definition, $entity): iterable
    {
        if ($entity instanceof Struct) {
            $entity = $entity->jsonSerialize();
        }

        yield from parent::serialize($config, $definition, $entity);

        if (isset($entity['media'])) {
            yield 'media' => implode('|', $this->getMediaUrls($entity));
        }

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
            $groups[$visibility['visibility']] ??= [];
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

    public function deserialize(Config $config, EntityDefinition $definition, $entity)
    {
        $entity = \is_array($entity) ? $entity : iterator_to_array($entity);

        $deserialized = parent::deserialize($config, $definition, $entity);
        $deserialized = \is_array($deserialized) ? $deserialized : iterator_to_array($deserialized);
        yield from $deserialized;

        if (Feature::isActive('v6.8.0.0') && ($deserialized['type'] ?? '') === '') {
            yield 'type' => ProductDefinition::TYPE_PHYSICAL;
        }

        $productId = $deserialized['id'] ?? null;

        $mapping = array_flip(self::VISIBILITY_MAPPING);

        $visibilities = [];

        $context = Context::createDefaultContext();

        $valuesByType = [];
        $salesChannelNames = [];

        foreach ($mapping as $key => $type) {
            if (!isset($entity['visibilities'][$key])) {
                continue;
            }

            $values = array_filter(explode('|', (string) $entity['visibilities'][$key]));
            $valuesByType[$type] = $values;

            foreach ($values as $value) {
                if (!Uuid::isValid($value)) {
                    $salesChannelNames[] = $value;
                }
            }
        }

        $idsByName = $this->mapSalesChannelNamesToIds($salesChannelNames, $context);

        foreach ($valuesByType as $type => $values) {
            foreach ($this->toSalesChannelIds($values, $idsByName) as $salesChannelId) {
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
            yield 'visibilities' => $this->findVisibilityIds($productId, $visibilities, $context);
        }

        try {
            yield 'media' => $this->convertMediaStringToArray($config, $definition, $entity, $deserialized, $context);
        } catch (\Throwable $exception) {
            yield '_error' => $exception;
        }

        if (isset($deserialized['id'], $deserialized['cover']['media']['id'])) {
            yield 'cover' => $this->findCoverProductMediaId($deserialized['id'], $deserialized['cover'], $context);
        }

        if (($deserialized['parentId'] ?? '') !== '' && ($deserialized['options'] ?? []) !== []) {
            yield 'configuratorSettings' => $this->findConfiguratorSettings($deserialized['parentId'], $deserialized['options'], $context);
        }
    }

    public function supports(string $entity): bool
    {
        return $entity === ProductDefinition::ENTITY_NAME;
    }

    /**
     * @param array<array<string, mixed>> $visibilities
     *
     * @return array<array<string, mixed>>
     */
    private function findVisibilityIds(?string $productId, array $visibilities, Context $context): array
    {
        if ($productId === null || $productId === '') {
            return $visibilities;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('productId', $productId));
        $criteria->addFilter(new EqualsAnyFilter('salesChannelId', array_column($visibilities, 'salesChannelId')));

        $existingIds = [];
        foreach ($this->visibilityRepository->search($criteria, $context)->getEntities() as $existing) {
            $existingIds[$existing->getSalesChannelId()] = $existing->getId();
        }

        foreach ($visibilities as $i => $visibility) {
            if (isset($existingIds[$visibility['salesChannelId']])) {
                $visibilities[$i]['id'] = $existingIds[$visibility['salesChannelId']];
            }
        }

        return $visibilities;
    }

    /**
     * @param list<string> $names
     *
     * @return array<string, list<string>>
     */
    private function mapSalesChannelNamesToIds(array $names, Context $context): array
    {
        $names = array_values(array_unique($names));

        if ($names === []) {
            return [];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('name', $names));

        $salesChannels = $this->salesChannelRepository->search($criteria, $context)->getEntities();

        $idsByName = [];

        foreach ($names as $name) {
            foreach ($salesChannels as $salesChannel) {
                if (mb_strtolower((string) $salesChannel->getName()) === mb_strtolower($name)) {
                    $idsByName[$name][] = $salesChannel->getId();
                }
            }
        }

        return $idsByName;
    }

    /**
     * @param array<string> $values
     * @param array<string, list<string>> $idsByName
     *
     * @return array<string>
     */
    private function toSalesChannelIds(array $values, array $idsByName): array
    {
        $ids = [];
        $resolved = [];

        foreach ($values as $value) {
            if (Uuid::isValid($value)) {
                $ids[] = $value;

                continue;
            }

            foreach ($idsByName[$value] ?? [] as $id) {
                $resolved[] = $id;
            }
        }

        return array_unique(array_merge($ids, $resolved));
    }

    /**
     * @param array<string, mixed> $cover
     *
     * @return array<string, mixed>
     */
    private function findCoverProductMediaId(string $productId, array $cover, Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('productId', $productId));
        $criteria->addFilter(new EqualsFilter('mediaId', $cover['media']['id']));

        $id = $this->productMediaRepository->searchIds($criteria, $context)->firstId();

        if ($id) {
            $cover['id'] = $id;
        }

        return $cover;
    }

    /**
     * @param list<array{id: string}> $options
     *
     * @return list<array{optionId: string, product: array{id: string}, id?: string}>
     */
    private function findConfiguratorSettings(string $parentId, array $options, Context $context): array
    {
        $optionIds = [];

        foreach ($options as $option) {
            if (($option['id'] ?? '') !== '') {
                $optionIds[] = $option['id'];
            }
        }

        if ($optionIds === []) {
            return [];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('productId', $parentId));
        $criteria->addFilter(new EqualsAnyFilter('optionId', $optionIds));

        $existingIds = [];
        foreach ($this->productConfiguratorSettingRepository->search($criteria, $context)->getEntities() as $existing) {
            $existingIds[$existing->getOptionId()] = $existing->getId();
        }

        $configuratorSettings = [];

        foreach ($optionIds as $optionId) {
            $configuratorSetting = [
                'optionId' => $optionId,
                'product' => [
                    'id' => $parentId,
                ],
            ];

            if (isset($existingIds[$optionId])) {
                $configuratorSetting['id'] = $existingIds[$optionId];
            }

            $configuratorSettings[] = $configuratorSetting;
        }

        return $configuratorSettings;
    }

    /**
     * @param array<string, mixed> $entity
     * @param array<string, mixed> $deserialized
     *
     * @return list<array<string, mixed>>
     */
    private function convertMediaStringToArray(
        Config $config,
        EntityDefinition $definition,
        array $entity,
        array $deserialized,
        Context $context
    ): array {
        if (($entity['media'] ?? '') === '' || $entity['media'] === []) {
            return [];
        }

        $productMedias = [];
        $urls = explode('|', (string) $entity['media']);

        $productMediaField = $definition->getField('media');

        if (!$productMediaField instanceof OneToManyAssociationField) {
            return [];
        }

        $mediaField = $productMediaField->getReferenceDefinition()->getField('media');

        if (!$mediaField instanceof ManyToOneAssociationField) {
            return [];
        }

        $mediaDefinition = $mediaField->getReferenceDefinition();
        $mediaSerializer = $this->serializerRegistry->getEntity($mediaDefinition->getEntityName());

        $mediaIds = [];

        foreach ($urls as $url) {
            $deserializedMedia = $mediaSerializer->deserialize($config, $mediaDefinition, [
                'url' => $url,
            ]);
            $deserializedMedia = \is_array($deserializedMedia) ? $deserializedMedia : iterator_to_array($deserializedMedia);

            if (isset($deserializedMedia['_error']) && $deserializedMedia['_error'] instanceof \Throwable) {
                throw $deserializedMedia['_error'];
            }

            $productMedias[] = ['media' => $deserializedMedia];

            if (isset($deserializedMedia['id'])) {
                $mediaIds[] = $deserializedMedia['id'];
            }
        }

        $productId = $deserialized['id'] ?? null;

        if ($productId === null || $productId === '' || $mediaIds === []) {
            return $productMedias;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('productId', $productId));
        $criteria->addFilter(new EqualsAnyFilter('media.id', $mediaIds));

        $existingIds = [];

        foreach ($this->productMediaRepository->search($criteria, $context)->getEntities() as $existing) {
            $existingIds[$existing->getMediaId()] = $existing->getId();
        }

        foreach ($productMedias as $i => $productMedia) {
            $mediaId = $productMedia['media']['id'] ?? null;

            if ($mediaId !== null && isset($existingIds[$mediaId])) {
                $productMedias[$i]['id'] = $existingIds[$mediaId];
            }
        }

        return $productMedias;
    }

    /**
     * @param array<string, mixed> $entity
     *
     * @return array<int, string>
     */
    private function getMediaUrls(array $entity): array
    {
        if (!isset($entity['media'])) {
            return [];
        }

        $productMedias = $entity['media'];
        if ($productMedias instanceof Struct) {
            $productMedias = $productMedias->jsonSerialize();
        }

        $urls = [];
        $coverUrl = null;

        if ($productMedias !== [] && ($entity['cover'] ?? null) !== null && $entity['cover'] !== []) {
            $coverMedia = $entity['cover'] instanceof ProductMediaEntity
                ? $entity['cover']->jsonSerialize()
                : $entity['cover'];
            $coverUrl = $coverMedia['media'] instanceof MediaEntity
                ? $coverMedia['media']->jsonSerialize()['url']
                : $coverMedia['media']['url'];
        }

        foreach ($productMedias as $productMedia) {
            $productMedia = $productMedia instanceof ProductMediaEntity
                ? $productMedia->jsonSerialize()
                : $productMedia;

            if (($productMedia['media'] ?? []) === []) {
                continue;
            }

            $media = $productMedia['media'] instanceof MediaEntity
                ? $productMedia['media']->jsonSerialize()
                : $productMedia['media'];

            if ($media['url'] === $coverUrl) {
                continue;
            }

            $urls[(int) $productMedia['position']] = (string) $media['url'];
        }

        ksort($urls);

        return $urls;
    }
}
