<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\DataAbstractionLayer;

use Shopware\Core\Content\ProductExport\Exception\DuplicateFileNameException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface;
use Shopware\Core\Framework\Log\Package;

#[Package('sales-channel')]
class ProductExportExceptionHandler implements ExceptionHandlerInterface
{
    public function matchException(\Exception $e): ?\Exception
    {
        if (preg_match('/SQLSTATE\[23000\]:.*1062 Duplicate.*file_name\'/', $e->getMessage())) {
            $file = [];
            preg_match('/Duplicate entry \'(.*)\' for key/', $e->getMessage(), $file);
            $file = $file[1];

            return new DuplicateFileNameException($file, $e);
        }

        return null;
    }

    public function getPriority(): int
    {
        return ExceptionHandlerInterface::PRIORITY_DEFAULT;
    }
}
