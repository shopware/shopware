<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Cms;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\Struct\TextStruct;
use Shopware\Core\Framework\Log\Package;

#[Package('discovery')]
class CategoryNameCmsElementResolver extends AbstractCmsElementResolver
{
    public function getType(): string
    {
        return 'category-name';
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        return null;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $text = new TextStruct();
        $slot->setData($text);

        $contentConfig = $slot->getFieldConfig()->get('content');
        if ($contentConfig === null) {
            return;
        }

        if ($contentConfig->isStatic()) {
            $content = $contentConfig->getStringValue();

            if ($resolverContext instanceof EntityResolverContext) {
                $content = (string) $this->resolveEntityValues($resolverContext, $content);
            }

            $text->setContent($content);

            return;
        }

        if ($contentConfig->isMapped() && $resolverContext instanceof EntityResolverContext) {
            $content = $this->resolveEntityValue($resolverContext->getEntity(), $contentConfig->getStringValue());

            $text->setContent((string) $content);
        }
    }
}
