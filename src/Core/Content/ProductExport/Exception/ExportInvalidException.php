<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Exception;

use Shopware\Core\Content\ProductExport\Error\Error;
use Shopware\Core\Content\ProductExport\Error\ErrorMessage;
use Shopware\Core\Framework\ShopwareHttpException;

class ExportInvalidException extends ShopwareHttpException
{
    /** @var ErrorMessage[] */
    protected $errorMessages;

    /**
     * @param Error[] $errors
     */
    public function __construct(array $errors)
    {
        $errorMessages = array_merge(
            ...array_map(
                function (Error $error) {
                    return $error->getErrorMessages();
                },
                $errors
            )
        );

        $this->errorMessages = $errorMessages;

        parent::__construct(sprintf('Export file generation resulted in validation errors'), ['errors' => $errors, 'errorMessages' => $errorMessages]);
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
