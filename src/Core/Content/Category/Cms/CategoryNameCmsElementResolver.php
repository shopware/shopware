<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Cms;

use Shopware\Core\Content\Cms\DataResolver\Element\TextCmsElementResolver;
use Shopware\Core\Framework\Log\Package;

#[Package('discovery')]
class CategoryNameCmsElementResolver extends TextCmsElementResolver
{
    public function getType(): string
    {
        return 'category-name';
    }
}
