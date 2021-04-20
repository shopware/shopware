<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Csrf\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class CsrfNotEnabledException extends ShopwareHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('CSRF protection is not enabled.', [], $previous);
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__CSRF_NOT_ENABLED';
    }
}
