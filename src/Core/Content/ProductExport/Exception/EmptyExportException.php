<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Exception;

use Shopware\Core\Content\ProductExport\ProductExportException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.6.0 - will be removed, use ProductExportException::productExportNotFound instead
 */
#[Package('inventory')]
class EmptyExportException extends ProductExportException
{
    public function __construct(?string $id = null)
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0', 'use ProductExportException::productExportNotFound instead')
        );

        if ($id) {
            parent::__construct(
                Response::HTTP_NOT_FOUND,
                self::PRODUCT_EXPORT_NOT_FOUND,
                'No products for export with ID {{ id }} found',
                ['id' => $id]
            );
        } else {
            parent::__construct(
                Response::HTTP_NOT_FOUND,
                self::PRODUCT_EXPORT_NOT_FOUND,
                'No products for export found'
            );
        }
    }

    public function getStatusCode(): int
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0', 'use ProductExportException::productExportNotFound instead')
        );

        return Response::HTTP_NOT_FOUND;
    }

    public function getErrorCode(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0', 'use ProductExportException::productExportNotFound instead')
        );

        return 'CONTENT__PRODUCT_EXPORT_EMPTY';
    }
}
