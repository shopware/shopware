<?php declare(strict_types=1);

namespace Shopware\Core\System\Locale\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('discovery')]
class InvalidLocaleCodeException extends ShopwareHttpException
{
    public function __construct(string $code)
    {
        parent::__construct('Cannot create or update locale with invalid code "{{ code }}"', ['code' => $code]);
    }

    public function getErrorCode(): string
    {
        return 'SYSTEM__INVALID_LOCALE_CODE';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
