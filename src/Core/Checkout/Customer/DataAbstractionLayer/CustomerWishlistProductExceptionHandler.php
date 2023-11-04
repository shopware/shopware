<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\DataAbstractionLayer;

use Shopware\Core\Checkout\Customer\Exception\DuplicateWishlistProductException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class CustomerWishlistProductExceptionHandler implements ExceptionHandlerInterface
{
    public function getPriority(): int
    {
        return ExceptionHandlerInterface::PRIORITY_DEFAULT;
    }

    public function matchException(\Exception $e): ?\Exception
    {
        if (preg_match('/SQLSTATE\[23000\]:.*1062 Duplicate.*uniq.customer_wishlist.sales_channel_id__customer_id\'/', $e->getMessage())) {
            return new DuplicateWishlistProductException();
        }

        return null;
    }
}
