<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Exception;

use Shopware\Core\Content\Sitemap\SitemapException;
use Shopware\Core\Framework\Log\Package;

#[Package('discovery')]
class SitemapAlreadyLockedException extends SitemapException
{
}
