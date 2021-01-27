<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cms;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\Struct\TextStruct;

class ProductNameCmsElementResolver extends AbstractProductDetailCmsElementResolver
{
    public function getType(): string
    {
        return 'product-name';
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $text = new TextStruct();
        $slot->setData($text);

        $config = $slot->getFieldConfig();

        $contentConfig = $config->get('content');

        if (!$contentConfig) {
            return;
        }

        if ($contentConfig->isStatic()) {
            $content = (string) $contentConfig->getValue();

            if ($resolverContext instanceof EntityResolverContext) {
                $content = $this->resolveEntityValues($resolverContext, $content);
            }

            $text->setContent((string) $content);

            return;
        }

        if ($contentConfig->isMapped() && $resolverContext instanceof EntityResolverContext) {
            $content = $this->resolveEntityValue($resolverContext->getEntity(), $contentConfig->getValue());

            $text->setContent((string) $content);
        }
    }
}
