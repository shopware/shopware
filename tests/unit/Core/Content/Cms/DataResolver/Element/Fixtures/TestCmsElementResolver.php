<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cms\DataResolver\Element\Fixtures;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

/**
 * @internal
 */
class TestCmsElementResolver extends AbstractCmsElementResolver
{
    public function runResolveEntityValue(?Entity $entity, string $path): mixed
    {
        return $this->resolveEntityValue($entity, $path);
    }

    public function runResolveEntityValueToString(?Entity $entity, string $path, EntityResolverContext $resolverContext): string
    {
        return $this->resolveEntityValueToString($entity, $path, $resolverContext);
    }

    public function runResolveDefinitionField(EntityDefinition $definition, string $path): ?Field
    {
        return $this->resolveDefinitionField($definition, $path);
    }

    public function runResolveCriteriaForLazyLoadedRelations(EntityResolverContext $resolverContext, FieldConfig $config): ?Criteria
    {
        return $this->resolveCriteriaForLazyLoadedRelations($resolverContext, $config);
    }

    public function getType(): string
    {
        return 'abstract-test';
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        return null;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
    }
}
