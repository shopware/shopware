<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Captcha\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\Captcha\AbstractCaptcha;
use Shopware\Storefront\Framework\Captcha\CaptchaException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.7.0 - Will be removed, use CaptchaException::invalid instead
 */
#[Package('storefront')]
class CaptchaInvalidException extends CaptchaException
{
    public function __construct(AbstractCaptcha $captcha)
    {
        parent::__construct(
            Response::HTTP_FORBIDDEN,
            'FRAMEWORK__INVALID_CAPTCHA_VALUE',
            'The provided value for captcha "{{ captcha }}" is not valid.',
            [
                'captcha' => $captcha::class,
            ]
        );
    }
}
