<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\DataAbstractionLayer;

use Shopware\Core\Content\ProductExport\Exception\DuplicateFileNameException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;

class ProductExportExceptionHandler implements ExceptionHandlerInterface
{
    public function matchException(\Exception $e, WriteCommand $command): ?\Exception
    {
        if ($e->getCode() !== 0 || $command->getDefinition()->getEntityName() !== 'product_export') {
            return null;
        }

        if (preg_match('/SQLSTATE\[23000\]:.*1062 Duplicate.*\'file_name\'/', $e->getMessage())) {
            $payload = $command->getPayload();

            return new DuplicateFileNameException($payload['file_name'] ?? '', $e);
        }

        return null;
    }

    public function getPriority(): int
    {
        return ExceptionHandlerInterface::PRIORITY_DEFAULT;
    }
}
