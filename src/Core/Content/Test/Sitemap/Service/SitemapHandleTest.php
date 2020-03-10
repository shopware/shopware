<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Sitemap\Service;

use League\Flysystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Sitemap\Service\SitemapHandle;
use Shopware\Core\Content\Sitemap\Struct\Url;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SitemapHandleTest extends TestCase
{
    /**
     * @var SitemapHandle
     */
    private $handle;

    public function testWriteWithoutFinish(): void
    {
        $url = new Url();
        $url->setLoc('https://shopware.com');
        $url->setLastmod(new \DateTime());
        $url->setChangefreq('weekly');
        $url->setResource(CategoryEntity::class);
        $url->setIdentifier(Uuid::randomHex());

        $fileSystem = $this->createMock(Filesystem::class);
        $fileSystem->expects(static::never())->method('write');

        $this->handle = new SitemapHandle($fileSystem, $this->getContext());

        $this->handle->write([
            $url,
        ]);
    }

    public function testWrite(): void
    {
        $url = new Url();
        $url->setLoc('https://shopware.com');
        $url->setLastmod(new \DateTime());
        $url->setChangefreq('weekly');
        $url->setResource(CategoryEntity::class);
        $url->setIdentifier(Uuid::randomHex());

        $fileSystem = $this->createMock(Filesystem::class);
        $fileSystem->expects(static::once())->method('write');
        $fileSystem->method('listContents')->willReturn([]);

        $this->handle = new SitemapHandle($fileSystem, $this->getContext());

        $this->handle->write([$url]);
        $this->handle->finish();
    }

    public function testWrite101kItems(): void
    {
        $url = new Url();
        $url->setLoc('https://shopware.com');
        $url->setLastmod(new \DateTime());
        $url->setChangefreq('weekly');
        $url->setResource(CategoryEntity::class);
        $url->setIdentifier(Uuid::randomHex());

        $list = [];

        for ($i = 1; $i <= 101000; ++$i) {
            $list[] = clone $url;
        }

        $fileSystem = $this->createMock(Filesystem::class);
        $fileSystem->expects(static::atLeast(3))->method('write');
        $fileSystem->method('listContents')->willReturn([]);

        $this->handle = new SitemapHandle($fileSystem, $this->getContext());

        $this->handle->write($list);
        $this->handle->finish();
    }

    private function getContext(): SalesChannelContext
    {
        return $this->createMock(SalesChannelContext::class);
    }
}
