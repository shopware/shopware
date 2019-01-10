<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\ContentHome;

use Shopware\Core\Checkout\CheckoutContext;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ContentHomePageletLoader
{
    /**
     * @var ContainerInterface
     */
    private $container;

    public function __construct()
    {
    }

    /**
     * @param ContainerInterface|null $container
     */
    public function setContainer(ContainerInterface $container = null): void
    {
        $this->container = $container;
    }

    /**
     * @param ContentHomePageletRequest $request
     * @param CheckoutContext           $context
     *
     * @return ContentHomePageletStruct
     */
    public function load(ContentHomePageletRequest $request, CheckoutContext $context): ContentHomePageletStruct
    {
        return new ContentHomePageletStruct();
    }
}
