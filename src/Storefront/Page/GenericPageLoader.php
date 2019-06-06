<?php declare(strict_types=1);

namespace Shopware\Storefront\Page;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Pagelet\Footer\FooterPageletLoader;
use Shopware\Storefront\Pagelet\Header\HeaderPageletLoader;
use Symfony\Component\HttpFoundation\Request;

class GenericPageLoader
{
    /**
     * @var HeaderPageletLoader
     */
    private $headerLoader;

    /**
     * @var FooterPageletLoader
     */
    private $footerLoader;

    public function __construct(HeaderPageletLoader $headerLoader, FooterPageletLoader $footerLoader)
    {
        $this->headerLoader = $headerLoader;
        $this->footerLoader = $footerLoader;
    }

    public function load(Request $request, SalesChannelContext $context): Page
    {
        $page = new Page();

        if (!$request->isXmlHttpRequest()) {
            $page->setHeader(
                $this->headerLoader->load($request, $context)
            );

            $page->setFooter(
                $this->footerLoader->load($request, $context)
            );
        }

        return $page;
    }
}
