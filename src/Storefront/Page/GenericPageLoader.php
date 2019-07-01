<?php declare(strict_types=1);

namespace Shopware\Storefront\Page;

use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
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

    /**
     * @throws CategoryNotFoundException
     * @throws InconsistentCriteriaIdsException
     * @throws MissingRequestParameterException
     */
    public function load(Request $request, SalesChannelContext $salesChannelContext): Page
    {
        $page = new Page();

        if (!$request->isXmlHttpRequest()) {
            $page->setHeader(
                $this->headerLoader->load($request, $salesChannelContext)
            );

            $page->setFooter(
                $this->footerLoader->load($request, $salesChannelContext)
            );
        }

        return $page;
    }
}
