<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Captcha;

use Shopware\Storefront\Framework\Captcha\BasicCaptcha\BasicCaptchaImage;
use Shopware\Storefront\Pagelet\Pagelet;

/**
 * @internal (flag:FEATURE_NEXT_12455)
 */
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
