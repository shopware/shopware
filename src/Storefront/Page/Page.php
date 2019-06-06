<?php declare(strict_types=1);

namespace Shopware\Storefront\Page;

use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Pagelet\Footer\FooterPagelet;
use Shopware\Storefront\Pagelet\Header\HeaderPagelet;

class Page extends Struct
{
    /**
     * @var HeaderPagelet|null
     */
    protected $header;

    /**
     * @var FooterPagelet|null
     */
    protected $footer;

    public function getHeader(): ?HeaderPagelet
    {
        return $this->header;
    }

    public function setHeader(?HeaderPagelet $header): void
    {
        $this->header = $header;
    }

    public function getFooter(): ?FooterPagelet
    {
        return $this->footer;
    }

    public function setFooter(?FooterPagelet $footer): void
    {
        $this->footer = $footer;
    }
}
