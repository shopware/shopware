<?php declare(strict_types=1);

namespace Shopware\Core\System\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class InvalidLocaleCodeException extends ShopwareHttpException
{
    protected $code = 'INVALID-LOCALE-CODE';

    public function __construct(string $localeCode, int $code = 0, Throwable $previous = null)
    {
        $message = sprintf('Locale with code "%s" could not be found.', $localeCode);
        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
