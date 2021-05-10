<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Captcha;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal (flag:FEATURE_NEXT_12455)
 */
abstract class AbstractBasicCaptchaPageletLoader
{
    abstract public function load(Request $request, SalesChannelContext $context): BasicCaptchaPagelet;
}
