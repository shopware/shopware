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

    public function load(Request $request, SalesChannelContext $context, $loadHeader = true, $loadFooter = true): Page
    {
        $loadFooter = $request->isXmlHttpRequest() ? false : $loadFooter;
        $loadHeader = $request->isXmlHttpRequest() ? false : $loadHeader;

        $page = new Page($context);

        if ($loadHeader) {
            $page->setHeader(
                $this->headerLoader->load($request, $context)
            );
        }

        if ($loadFooter) {
            $page->setFooter(
                $this->footerLoader->load($request, $context)
            );
        }

        return $page;
    }
}
