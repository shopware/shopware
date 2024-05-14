<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.7.0 - Will be removed. Use ImportExportException::fileEmpty instead
 */
#[Package('services-settings')]
class FileEmptyException extends ShopwareHttpException
{
    public function __construct(string $filename)
    {
        parent::__construct('The file {{ filename }} is empty.', ['filename' => $filename]);
    }

    public function getStatusCode(): int
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.7.0.0', 'ImportExportException::fileEmpty')
        );

        return Response::HTTP_BAD_REQUEST;
    }

    public function getErrorCode(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.7.0.0', 'ImportExportException::fileEmpty')
        );

        return 'CONTENT__IMPORT_EXPORT_FILE_EMPTY';
    }
}
