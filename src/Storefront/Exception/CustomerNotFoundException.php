<?php declare(strict_types=1);

namespace Shopware\Storefront\Exception;

use Shopware\Framework\ShopwareException;
use Throwable;

class CustomerNotFoundException extends \Exception implements ShopwareException
{
    public function __construct(string $email, int $code = 0, Throwable $previous = null)
    {
        $message = sprintf('No matching customer for email "%s" was found.', $email);

        parent::__construct($message, $code, $previous);
    }
}
