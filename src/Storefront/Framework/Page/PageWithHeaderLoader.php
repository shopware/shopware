<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Page;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Pagelet\Header\HeaderPageletLoader;
use Symfony\Component\HttpFoundation\Request;

class PageWithHeaderLoader implements PageLoaderInterface
{
    /**
     * @var HeaderPageletLoader|PageLoaderInterface
     */
    private $headerLoader;

    public function __construct(PageLoaderInterface $headerLoader)
    {
        $this->headerLoader = $headerLoader;
    }

    public function load(Request $request, SalesChannelContext $context): PageWithHeader
    {
        $header = $this->headerLoader->load($request, $context);

        return new PageWithHeader($header, $context);
    }
}
