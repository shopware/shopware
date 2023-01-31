<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Captcha;

use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\Captcha\BasicCaptcha\BasicCaptchaImage;
use Shopware\Storefront\Pagelet\Pagelet;

#[Package('storefront')]
class BasicCaptchaPagelet extends Pagelet
{
    protected BasicCaptchaImage $captcha;

    public function setCaptcha(BasicCaptchaImage $captcha): void
    {
        $this->captcha = $captcha;
    }

    public function getCaptcha(): BasicCaptchaImage
    {
        return $this->captcha;
    }
}
