<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Navigation\Error;

use Shopware\Core\System\Annotation\Concept\ExtensionPattern\Decoratable;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Decoratable()
 */
interface ErrorPageLoaderInterface
{
    public function load(string $cmsErrorLayoutId, Request $request, SalesChannelContext $context): ErrorPage;
}
