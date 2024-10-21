<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Exception;

use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

/**
 * @deprecated tag:v6.7.0 - Will be removed. Use DataAbstractionLayerException::parentFieldNotFound instead
 */
#[Package('core')]
class ParentFieldNotFoundException extends ShopwareHttpException
{
    public function __construct(EntityDefinition $definition)
    {
        parent::__construct(
            'Can not find parent property \'parent\' field for definition {{ definition }',
            ['definition' => $definition->getEntityName()]
        );
    }

    public function getErrorCode(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.7.0.0', 'DataAbstractionLayerException::parentFieldNotFound'),
        );

        return DataAbstractionLayerException::PARENT_FIELD_NOT_FOUND_EXCEPTION;
    }
}
