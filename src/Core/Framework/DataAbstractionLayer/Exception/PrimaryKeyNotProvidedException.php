<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Exception;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\ShopwareHttpException;

class PrimaryKeyNotProvidedException extends ShopwareHttpException
{
    public function __construct(EntityDefinition $definition, Field $field)
    {
        parent::__construct(
            'Expected primary key field {{ propertyName }} for definition {{ definition }} not provided',
            ['definition' => $definition->getClass(), 'propertyName' => $field->getPropertyName()]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__PRIMARY_KEY_NOT_PROVIDED';
    }
}
