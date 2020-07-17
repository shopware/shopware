<?php declare(strict_types=1);

namespace Shopware\Recovery\Exception;

use Symfony\Component\HttpFoundation\Response;

class LanguageNotFoundException extends \Exception
{
    public function __construct(string $message)
    {
        parent::__construct(
            '{{ message }}',
            ['message' => $message]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__LANGUAGE_NOT_FOUND';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
