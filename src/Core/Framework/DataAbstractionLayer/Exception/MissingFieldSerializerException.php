<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Exception;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\ShopwareHttpException;

class MissingFieldSerializerException extends ShopwareHttpException
{
    public function __construct(Field $field)
    {
        parent::__construct('No field serializer class found for field class "{{ class }}".', ['class' => \get_class($field)]);
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__MISSING_FIELD_SERIALIZER';
    }
}
