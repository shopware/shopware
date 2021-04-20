<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class FileNotReadableException extends ShopwareHttpException
{
    public function __construct(string $path, ?\Throwable $previous = null)
    {
        parent::__construct('Import file is not readable at {{ path }}.', ['path' => $path], $previous);
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__IMPORT_FILE_IS_NOT_READABLE';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_INTERNAL_SERVER_ERROR;
    }
}
