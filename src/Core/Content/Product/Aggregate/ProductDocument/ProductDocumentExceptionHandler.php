<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductDocument;

use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('inventory')]
class ProductDocumentExceptionHandler implements ExceptionHandlerInterface
{
    public function getPriority(): int
    {
        return ExceptionHandlerInterface::PRIORITY_DEFAULT;
    }

    public function matchException(\Throwable $e): ?\Throwable
    {
        if (preg_match('/SQLSTATE\[23000]:.*1062 Duplicate.*uniq\.product_document\.product_language_media\'/', $e->getMessage())) {
            return ProductException::duplicateDocument();
        }

        return null;
    }
}
