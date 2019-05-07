<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Page;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Pagelet\Footer\FooterPageletLoader;
use Shopware\Storefront\Pagelet\Header\HeaderPageletLoader;
use Symfony\Component\HttpFoundation\Request;

class PageWithHeaderLoader implements PageLoaderInterface
{
    /**
     * @var HeaderPageletLoader|PageLoaderInterface
     */
    private $headerLoader;

    /**
     * @var FooterPageletLoader|PageLoaderInterface
     */
    private $footerLoader;

    public function __construct(PageLoaderInterface $headerLoader, PageLoaderInterface $footerLoader)
    {
        $this->headerLoader = $headerLoader;
        $this->footerLoader = $footerLoader;
    }

    public function load(Request $request, SalesChannelContext $context): PageWithHeader
    {
        $header = $this->headerLoader->load($request, $context);

        $footer = $this->footerLoader->load($request, $context);

        return new PageWithHeader($header, $footer, $context);
    }
}
