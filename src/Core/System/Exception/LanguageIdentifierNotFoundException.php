<?php declare(strict_types=1);

namespace Shopware\Core\System\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class LanguageIdentifierNotFoundException extends ShopwareHttpException
{
    protected $code = 'LANGUAGE-IDENTIFIER-NOT-FOUND';

    public function __construct(string $localeCode, int $code = 0, Throwable $previous = null)
    {
        $message = sprintf('Language with identifier "%s" could not be found.', $localeCode);
        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
