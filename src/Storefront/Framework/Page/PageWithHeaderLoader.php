<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Page;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Storefront\Pagelet\Header\HeaderPageletLoader;

class PageWithHeaderLoader implements PageLoaderInterface
{
    /**
     * @var HeaderPageletLoader
     */
    private $headerLoader;

    public function __construct(HeaderPageletLoader $headerLoader)
    {
        $this->headerLoader = $headerLoader;
    }

    /**
     * @param InternalRequest $request
     * @param CheckoutContext $context
     */
    public function load(InternalRequest $request, CheckoutContext $context)
    {
        $header = $this->headerLoader->load($request, $context);

        return new PageWithHeader($header, $context);
    }
}
