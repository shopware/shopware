<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\DataAbstractionLayer;

use Shopware\Core\Content\Category\CategoryException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
class CategoryNonExistentExceptionHandler implements ExceptionHandlerInterface
{
    public function getPriority(): int
    {
        return ExceptionHandlerInterface::PRIORITY_DEFAULT;
    }

    public function matchException(\Exception $e): ?\Exception
    {
        if (preg_match('/SQLSTATE\[23000\]:.*1452 Cannot add or update a child row: a foreign key constraint fails.*category\.after_category_id/', $e->getMessage())) {
            return CategoryException::afterCategoryNotFound();
        }

        return null;
    }
}
