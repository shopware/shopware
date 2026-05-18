<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\UrlProvider;

use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
enum UrlType
{
    case HOME;
    case CATEGORY;
    case LANDING_PAGE;
    case PRODUCT;
}
