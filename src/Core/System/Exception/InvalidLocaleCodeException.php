<?php declare(strict_types=1);

namespace Shopware\Core\System\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class InvalidLocaleCodeException extends ShopwareHttpException
{
    public function __construct(string $localeCode)
    {
        parent::__construct(
            'Locale with code "{{ locale }}" could not be found.',
            ['locale' => $localeCode]
        );
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
