<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Exception;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\ShopwareHttpException;

class DecodeByHydratorException extends ShopwareHttpException
{
    public function __construct(Field $field, int $code = 0, \Throwable $previous = null)
    {
        $message = sprintf('Decode of field %s are handled by EntityHydrator', get_class($field));

        parent::__construct($message, $code, $previous);
    }
}
