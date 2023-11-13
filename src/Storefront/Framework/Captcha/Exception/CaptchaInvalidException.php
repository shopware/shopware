<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Captcha\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Shopware\Storefront\Framework\Captcha\AbstractCaptcha;
use Symfony\Component\HttpFoundation\Response;

#[Package('storefront')]
class CaptchaInvalidException extends ShopwareHttpException
{
    public function __construct(AbstractCaptcha $captcha)
    {
        parent::__construct(
            'The provided value for captcha "{{ captcha }}" is not valid.',
            [
                'captcha' => $captcha::class,
            ]
        );
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_FORBIDDEN;
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__INVALID_CAPTCHA_VALUE';
    }
}
