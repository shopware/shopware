<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Page;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Storefront\Pagelet\Header\HeaderPagelet;

class PageWithHeader extends GenericPage
{
    /**
     * @var HeaderPagelet
     */
    protected $header;

    /**
     * @var CheckoutContext
     */
    protected $context;

    public function __construct(HeaderPagelet $header, CheckoutContext $context)
    {
        $this->header = $header;
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
}
