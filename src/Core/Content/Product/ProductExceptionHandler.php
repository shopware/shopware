<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Shopware\Core\Content\Product\Exception\DuplicateProductNumberException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandInterface;

class ProductExceptionHandler implements ExceptionHandlerInterface
{
    public function matchException(\Exception $e, WriteCommandInterface $command): ?\Exception
    {
        if ($e->getCode() !== 0 || $command->getDefinition()->getEntityName() !== 'product') {
            return null;
        }

        if (preg_match('/SQLSTATE\[23000\]:.*1062 Duplicate.*\'uniq.product.product_number__version_id\'/', $e->getMessage())) {
            $payload = $command->getPayload();

            return new DuplicateProductNumberException($payload['product_number'] ?? '', $e);
        }

        return null;
    }
}
