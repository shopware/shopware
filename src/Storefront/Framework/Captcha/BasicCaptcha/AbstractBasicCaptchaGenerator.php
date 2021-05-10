<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Captcha\BasicCaptcha;

/**
 * @internal (flag:FEATURE_NEXT_12455)
 */
abstract class AbstractBasicCaptchaGenerator
{
    abstract public function generate(): BasicCaptchaImage;
}
