<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\DataAbstractionLayer;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerWishlistProduct\CustomerWishlistProductDefinition;
use Shopware\Core\Checkout\Customer\Exception\DuplicateWishlistProductException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Uuid\Uuid;

class CustomerWishlistProductExceptionHandler implements ExceptionHandlerInterface
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
        if (!Feature::isActive('FEATURE_NEXT_16640') && $command->getDefinition()->getEntityName() !== CustomerWishlistProductDefinition::ENTITY_NAME) {
            return null;
        }

        if (preg_match('/SQLSTATE\[23000\]:.*1062 Duplicate.*uniq.customer_wishlist.sales_channel_id__customer_id\'/', $e->getMessage())) {
            $productId = '';
            if (!Feature::isActive('FEATURE_NEXT_16640')) {
                $payload = $command->getPayload();
                $productId = !empty($payload['product_id']) ? Uuid::fromBytesToHex($payload['product_id']) : '';
            }

            return new DuplicateWishlistProductException($productId);
        }

        return null;
    }
}
