<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo;

use Shopware\Core\Content\Seo\Hreflang\HreflangCollection;

interface HreflangLoaderInterface
{
    public function load(HreflangLoaderParameter $parameter): HreflangCollection;
}
