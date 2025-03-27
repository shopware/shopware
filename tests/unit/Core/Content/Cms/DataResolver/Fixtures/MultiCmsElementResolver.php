<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cms\DataResolver\Fixtures;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\CmsElementResolverInterface;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

/**
 * @internal
 */
class MultiCmsElementResolver implements CmsElementResolverInterface
{
    public function __construct(
        private readonly string $type,
        private readonly string $entity
    ) {
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        $criteriaCollection = new CriteriaCollection();
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteriaCollection->add('fetch', $this->entity, $criteria);

        return $criteriaCollection;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        /** @var EntitySearchResult<EntityCollection<Entity>> $fetchResult */
        $fetchResult = $result->get('fetch');
        $slot->setData($fetchResult);
    }
}
