<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('sales-channel')]
class ExportNotFoundException extends ShopwareHttpException
{
    public function __construct(
        ?string $id = null,
        ?string $fileName = null
    ) {
        $message = 'No product exports found';

        if ($id) {
            $message = 'Product export with ID {{ id }} not found';
        } elseif ($fileName) {
            $message = 'Product export with file name {{ fileName }} not found. Please check your access key.';
        }

        parent::__construct($message, ['id' => $id, 'fileName' => $fileName]);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__PRODUCT_EXPORT_NOT_FOUND';
    }
}
