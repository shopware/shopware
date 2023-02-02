<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Navigation;

use Shopware\Core\System\Annotation\Concept\ExtensionPattern\Decoratable;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Decoratable()
 */
interface NavigationPageLoaderInterface
{
    public function load(Request $request, SalesChannelContext $context): NavigationPage;
}
