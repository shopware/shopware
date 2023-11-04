<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('system-settings')]
class FileNotReadableException extends ShopwareHttpException
{
    public function __construct(string $path)
    {
        parent::__construct('Import file is not readable at {{ path }}.', ['path' => $path]);
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
