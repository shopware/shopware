<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Captcha\BasicCaptcha;

use Shopware\Core\Framework\Struct\Struct;

class BasicCaptchaImage extends Struct
{
    private string $code;

    private string $imageBase64;

    public function __construct(string $code, string $imageBase64)
    {
        $this->code = $code;
        $this->imageBase64 = $imageBase64;
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
