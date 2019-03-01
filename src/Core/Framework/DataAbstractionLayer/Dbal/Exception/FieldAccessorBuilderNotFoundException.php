<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class FieldAccessorBuilderNotFoundException extends ShopwareHttpException
{
    public function __construct(string $field, int $code = 0, ?\Throwable $previous = null)
    {
        $message = sprintf('The field accessor builder for field %s was not found.', $field);

        parent::__construct($message, $code, $previous);
    }
}
