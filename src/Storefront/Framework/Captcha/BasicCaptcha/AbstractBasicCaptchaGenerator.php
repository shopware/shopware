<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Captcha\BasicCaptcha;

abstract class AbstractBasicCaptchaGenerator
{
    abstract public function generate(): BasicCaptchaImage;
}
