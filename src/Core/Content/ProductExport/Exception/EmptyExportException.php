<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class EmptyExportException extends ShopwareHttpException
{
    public function __construct(?string $id = null, ?\Throwable $previous = null)
    {
        if (empty($id)) {
            parent::__construct('No products for export found', [], $previous);
        } else {
            parent::__construct('No products for export with ID {{ id }} found', ['id' => $id], $previous);
        }
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__PRODUCT_EXPORT_EMPTY';
    }
}
