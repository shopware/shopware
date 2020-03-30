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
use Shopware\Core\Framework\Struct\Struct;

class ProductSerializer extends EntitySerializer
{
    public const VISIBILITY_MAPPING = [
        ProductVisibilityDefinition::VISIBILITY_ALL => 'all',
        ProductVisibilityDefinition::VISIBILITY_LINK => 'link',
        ProductVisibilityDefinition::VISIBILITY_SEARCH => 'search',
    ];

    /**
     * @var EntityRepositoryInterface
     */
    private $visibilityRepository;

    public function __construct(EntityRepositoryInterface $visibilityRepository)
    {
        $this->visibilityRepository = $visibilityRepository;
    }

    public function serialize(Config $config, EntityDefinition $definition, $value): iterable
    {
        if ($value instanceof Struct) {
            $value = $value->jsonSerialize();
        }

        yield from parent::serialize($config, $definition, $value);

        if (!isset($value['visibilities'])) {
            return;
        }

        $visibilities = $value['visibilities'];
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

    public function deserialize(Config $config, EntityDefinition $definition, $value)
    {
        $value = is_array($value) ? $value : iterator_to_array($value);

        yield from parent::deserialize($config, $definition, $value);

        $productId = $value['id'] ?? null;

        $mapping = array_flip(self::VISIBILITY_MAPPING);

        $visibilities = [];

        foreach ($mapping as $key => $type) {
            if (!isset($value['visibilities'][$key])) {
                continue;
            }

            $ids = array_filter(explode('|', $value['visibilities'][$key]));
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
}
