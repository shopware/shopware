<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Page;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Pagelet\Footer\FooterPagelet;
use Shopware\Storefront\Pagelet\Header\HeaderPagelet;

class PageWithHeader extends GenericPage
{
    /**
     * @var HeaderPagelet
     */
    protected $header;

    /**
     * @var FooterPagelet
     */
    protected $footer;

    public function __construct(HeaderPagelet $header, FooterPagelet $footer, SalesChannelContext $context)
    {
        $this->header = $header;
        $this->footer = $footer;
        parent::__construct($context);
    }

    public function getHeader(): HeaderPagelet
    {
        return $this->header;
    }

    public function setHeader(HeaderPagelet $header): void
    {
        $this->header = $header;
    }

    public function getFooter(): FooterPagelet
    {
        return $this->footer;
    }

    public function setFooter(FooterPagelet $footer): void
    {
        $this->footer = $footer;
    }
}
