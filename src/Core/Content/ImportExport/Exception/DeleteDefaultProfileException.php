<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class DeleteDefaultProfileException extends ShopwareHttpException
{
    public function __construct(array $ids, ?\Throwable $previous = null)
    {
        parent::__construct('Cannot delete system default import_export_profile', ['ids' => $ids], $previous);
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__IMPORT_EXPORT_DELETE_DEFAULT_PROFILE';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
