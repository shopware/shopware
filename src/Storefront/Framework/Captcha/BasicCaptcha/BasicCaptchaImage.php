<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Captcha\BasicCaptcha;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('storefront')]
class BasicCaptchaImage extends Struct
{
    public function __construct(
        private readonly string $code,
        private readonly string $imageBase64
    ) {
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function imageBase64(): string
    {
        return $this->imageBase64;
    }
}
