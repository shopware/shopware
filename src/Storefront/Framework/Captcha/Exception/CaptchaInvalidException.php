<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Captcha\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Shopware\Storefront\Framework\Captcha\AbstractCaptcha;
use Symfony\Component\HttpFoundation\Response;

class CaptchaInvalidException extends ShopwareHttpException
{
    public function __construct(AbstractCaptcha $captcha)
    {
        parent::__construct(
            'The provided value for captcha "{{ captcha }}" is not valid.',
            [
                'captcha' => get_class($captcha),
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
