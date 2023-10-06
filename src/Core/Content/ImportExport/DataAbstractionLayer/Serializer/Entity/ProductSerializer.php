<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityEntity;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Uuid\Uuid;

#[Package('core')]
class ProductSerializer extends EntitySerializer
{
    final public const VISIBILITY_MAPPING = [
        ProductVisibilityDefinition::VISIBILITY_ALL => 'all',
        ProductVisibilityDefinition::VISIBILITY_LINK => 'link',
        ProductVisibilityDefinition::VISIBILITY_SEARCH => 'search',
    ];

    /**
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $visibilityRepository,
        private readonly EntityRepository $salesChannelRepository,
        private readonly EntityRepository $productMediaRepository,
        private readonly EntityRepository $productConfiguratorSettingRepository
    ) {
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

            $ids = array_filter(explode('|', (string) $entity['visibilities'][$key]));

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

        try {
            yield 'media' => $this->convertMediaStringToArray($config, $definition, $entity, $deserialized);
        } catch (\Throwable $exception) {
            yield '_error' => $exception;
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

    private function convertMediaStringToArray(
        Config $config,
        EntityDefinition $definition,
        array $entity,
        array $deserialized
    ): array {
        if (empty($entity['media'])) {
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

        foreach ($urls as $url) {
            $deserializedMedia = $mediaSerializer->deserialize($config, $mediaDefinition, [
                'url' => $url,
            ]);
            $deserializedMedia = \is_array($deserializedMedia) ? $deserializedMedia : iterator_to_array($deserializedMedia);

            if (isset($deserializedMedia['_error']) && $deserializedMedia['_error'] instanceof \Throwable) {
                throw $deserializedMedia['_error'];
            }

            $productMedia = [
                'media' => $deserializedMedia,
            ];

            if (!isset($deserialized['id'])) {
                $productMedias[] = $productMedia;

                continue;
            }

            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('productId', $deserialized['id']));
            $criteria->addFilter(new EqualsFilter('media.id', $deserializedMedia['id']));

            $productMediaId = $this->productMediaRepository->searchIds($criteria, Context::createDefaultContext())->firstId();

            if ($productMediaId) {
                $productMedia['id'] = $productMediaId;
            }

            $productMedias[] = $productMedia;
        }

        return $productMedias;
    }

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

        if (!empty($productMedias) && !empty($entity['cover'])) {
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

            if (empty($productMedia['media'])) {
                continue;
            }

            $media = $productMedia['media'] instanceof MediaEntity
                ? $productMedia['media']->jsonSerialize()
                : $productMedia['media'];

            if ($media['url'] === $coverUrl) {
                continue;
            }

            $urls[$productMedia['position']] = $media['url'];
        }

        ksort($urls);

        return $urls;
    }
}
