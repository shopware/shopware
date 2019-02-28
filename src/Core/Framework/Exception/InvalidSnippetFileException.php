<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class InvalidSnippetFileException extends ShopwareHttpException
{
    protected $code = 'INVALID-SNIPPET-FILE';

    public function __construct(string $locale, int $code = 0, \Throwable $previous = null)
    {
        $message = sprintf('The base snippet file for locale %s is not registered', $locale);

        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
