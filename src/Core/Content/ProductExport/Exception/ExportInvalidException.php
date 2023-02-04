<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Exception;

use Shopware\Core\Content\ProductExport\Error\Error;
use Shopware\Core\Content\ProductExport\Error\ErrorMessage;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

#[Package('sales-channel')]
class ExportInvalidException extends ShopwareHttpException
{
    /**
     * @var ErrorMessage[]
     */
    protected $errorMessages;

    /**
     * @param Error[] $errors
     */
    public function __construct(
        ProductExportEntity $productExportEntity,
        array $errors
    ) {
        $errorMessages = array_merge(
            ...array_map(
                fn (Error $error) => $error->getErrorMessages(),
                $errors
            )
        );

        $this->errorMessages = $errorMessages;

        parent::__construct(
            sprintf(
                'Export file generation for product export %s (%s) resulted in validation errors',
                $productExportEntity->getId(),
                $productExportEntity->getFileName()
            ),
            ['errors' => $errors, 'errorMessages' => $errorMessages]
        );
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__PRODUCT_EXPORT_INVALID_CONTENT';
    }

    public function getErrorMessages(): array
    {
        return $this->errorMessages;
    }
}
