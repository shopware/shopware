<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Sorting;

use Shopware\Core\Content\Product\Exception\DuplicateProductSortingKeyException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\Feature;

class ProductSortingExceptionHandler implements ExceptionHandlerInterface
{
    public function getPriority(): int
    {
        return ExceptionHandlerInterface::PRIORITY_DEFAULT;
    }

    /**
     * @internal (flag:FEATURE_NEXT_16640) - second parameter WriteCommand $command will be removed
     */
    public function matchException(\Exception $e, ?WriteCommand $command = null): ?\Exception
    {
        if ($e->getCode() !== 0) {
            return null;
        }
        if (preg_match('/SQLSTATE\[23000\]:.*1062 Duplicate.*uniq.product_sorting.url_key\'/', $e->getMessage())) {
            $key = [];
            preg_match('/Duplicate entry \'(.*)\' for key/', $e->getMessage(), $key);
            $key = $key[1];

            return new DuplicateProductSortingKeyException($key, $e);
        }

        return null;
    }
}
