<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Exception;

use Shopware\Core\Framework\ShopwareException;

class MappingEntityClassesException extends \RuntimeException implements ShopwareException
{
    public function __construct(int $code = 0, ?\Throwable $previous = null)
    {
        $message = 'Mapping definition neither have entities nor collection.';

        parent::__construct($message, $code, $previous);
    }
}
