<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo;

use Shopware\Core\Content\Seo\Hreflang\HreflangCollection;
use Shopware\Core\Framework\Log\Package;

#[Package('buyers-experience')]
interface HreflangLoaderInterface
{
    public function load(HreflangLoaderParameter $parameter): HreflangCollection;
}
