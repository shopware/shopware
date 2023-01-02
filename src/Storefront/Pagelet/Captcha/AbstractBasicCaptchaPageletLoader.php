<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Captcha;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('storefront')]
abstract class AbstractBasicCaptchaPageletLoader
{
    abstract public function load(Request $request, SalesChannelContext $context): BasicCaptchaPagelet;
}
