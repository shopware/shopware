<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Exception;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\ShopwareHttpException;

class MissingFieldSerializerException extends ShopwareHttpException
{
    public function __construct(Field $field, int $code = 0, ?\Throwable $previous = null)
    {
        $message = sprintf('No field serializer class found for field class %s', get_class($field));

        parent::__construct($message, $code, $previous);
    }
}
