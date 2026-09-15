<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Sitemap\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Sitemap\Struct\Url;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(Url::class)]
class UrlTest extends TestCase
{
    public function testStringRepresentation(): void
    {
        $url = new Url();
        $url->setLoc('http://localhost:8000');
        $url->setLastmod(new \DateTime('2026-09-14 12:00:00'));
        $url->setChangefreq('daily');
        $url->setPriority(1);

        static::assertSame('<url><loc>http://localhost:8000</loc><lastmod>2026-09-14</lastmod><changefreq>daily</changefreq><priority>1.0</priority></url>', $url->__toString());
    }
}
