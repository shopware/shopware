<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('system-settings')]
class InvalidFileContentException extends ShopwareHttpException
{
    public function __construct(string $filename)
    {
        parent::__construct('The content of the file {{ filename }} is invalid.', ['fieldName' => $filename]);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__IMPORT_EXPORT_INVALID_FILE_CONTENT';
    }
}
