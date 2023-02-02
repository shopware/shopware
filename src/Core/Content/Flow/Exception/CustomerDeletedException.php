<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Exception;

class CustomerDeletedException extends \Exception
{
    public function __construct(string $orderId)
    {
        $message = sprintf('The Customer of Order Id %s has been deleted', $orderId);

        parent::__construct($message);
    }
}
