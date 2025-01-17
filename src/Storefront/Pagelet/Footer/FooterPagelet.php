<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Footer;

use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\Tree\Tree;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Pagelet\NavigationPagelet;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class FooterPagelet extends NavigationPagelet
{
    /**
     * @deprecated tag:v6.7.0 - reason:new-optional-parameter - Parameter serviceMenu will be required
     */
    public function __construct(
        ?Tree $navigation,
        protected ?CategoryCollection $serviceMenu = null,
    ) {
        parent::__construct($navigation);
    }

    /**
     * @deprecated tag:v6.7.0 - reason:return-type-change - Will only return CategoryCollection
     */
    public function getServiceMenu(): ?CategoryCollection
    {
        return $this->serviceMenu;
    }
}
