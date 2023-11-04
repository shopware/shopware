<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('system-settings')]
class FileNotFoundException extends ShopwareHttpException
{
    public function __construct(string $fileId)
    {
        parent::__construct('Cannot find import/export file with id {{ fileId }}', ['fileId' => $fileId]);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__IMPORT_EXPORT_FILE_NOT_FOUND';
    }
}
