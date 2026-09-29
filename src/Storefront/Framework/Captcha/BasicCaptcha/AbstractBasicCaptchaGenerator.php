<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Captcha\BasicCaptcha;

use Shopware\Core\Framework\Log\Package;

#[Package('discovery')]
abstract class AbstractBasicCaptchaGenerator
{
    abstract public function generate(): BasicCaptchaImage;
}
