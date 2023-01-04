<?php declare(strict_types=1);

namespace Shopware\Storefront\Page;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Annotation\Concept\ExtensionPattern\Decoratable;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Decoratable()
 */
#[Package('storefront')]
interface GenericPageLoaderInterface
{
    public function load(Request $request, SalesChannelContext $context): Page;
}
