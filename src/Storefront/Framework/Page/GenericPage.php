<?php

namespace Shopware\Storefront\Framework\Page;

use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Pagelet\Header\HeaderPagelet;

class GenericPage extends Struct
{
    /**
     * @var HeaderPagelet
     */
    protected $header;

    public function __construct(HeaderPagelet $header)
    {
        $this->header = $header;
    }

    public function getHeader(): HeaderPagelet
    {
        return $this->header;
    }
}