<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Type\CmsFixtures;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\CmsElementResolverInterface;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ImageStruct;
use Shopware\Core\Content\Cms\SalesChannel\Struct\TextStruct;
use Shopware\Core\Framework\Struct\ArrayStruct;

abstract class BaseResolver implements CmsElementResolverInterface
{
    public function __construct()
    {
        throw new \LogicException('Resolver inference must not instantiate services.');
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        return null;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $slot->setData(new TextStruct());
    }
}

class InheritedResolver extends BaseResolver
{
    public function getType(): string
    {
        return 'custom-inherited';
    }
}

class HelperResolver extends BaseResolver
{
    public function getType(): string
    {
        return 'custom-shape';
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $this->populate(unused: $slot->getSlot(), input: $slot);
    }

    private function populate(string $unused, CmsSlotEntity $input): void
    {
        $input->setData(new ArrayStruct(['visible' => true, 'count' => 3]));
    }
}

class UnionResolver extends BaseResolver
{
    public function getType(): string
    {
        return 'custom-union';
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        if ($resolverContext->getRequest()->query->getBoolean('image')) {
            $slot->setData(new ImageStruct());
        } else {
            parent::enrich($slot, $resolverContext, $result);
        }
    }
}

class AliasedResolver extends BaseResolver
{
    public function getType(): string
    {
        return 'custom-opaque';
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        parent::enrich($slot, $resolverContext, $result);
        $alias = $slot;
        $alias->setData(new ImageStruct());
    }
}

class ReplacementResolver extends BaseResolver
{
    public function getType(): string
    {
        return 'custom-shape';
    }
}

class DynamicNameResolver extends BaseResolver
{
    public function getType(): string
    {
        return getenv('CMS_ELEMENT_NAME') ?: 'dynamic';
    }
}

class ExternalHelperResolver extends BaseResolver
{
    public function getType(): string
    {
        return 'custom-external';
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        parent::enrich($slot, $resolverContext, $result);
        replaceData([$slot]);
    }
}

class CallableResolver extends BaseResolver
{
    public function getType(): string
    {
        return 'custom-callable';
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        parent::enrich($slot, $resolverContext, $result);
        $assign = $slot->setData(...);
        $assign(new ImageStruct());
    }
}

/**
 * @param non-empty-list<CmsSlotEntity> $slots
 */
function replaceData(array $slots): void
{
    $slots[0]->setData(new ImageStruct());
}
