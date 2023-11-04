<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('system-settings')]
class LogNotWritableException extends ShopwareHttpException
{
    public function __construct(array $ids)
    {
        parent::__construct('Entity import_export_log is write-protected if not in system scope', ['ids' => $ids]);
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__IMPORT_EXPORT_LOG_NOT_WRITABLE';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
