<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Exception;

use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

/**
 * @deprecated tag:v6.7.0 - Will be removed as mappings will get structs and collections
 */
#[Package('core')]
class MappingEntityClassesException extends ShopwareHttpException
{
    public function __construct()
    {
        parent::__construct('Mapping definition neither have entities nor collection.');
    }

    public function getErrorCode(): string
    {
        if (EnvironmentHelper::getVariable('APP_ENV') === 'dev') {
            Feature::triggerDeprecationOrThrow('v6.7.0.0', Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.7.0.0', 'Class will be removed'));
        }

        return 'FRAMEWORK__MAPPING_ENTITY_DEFINITION_CLASSES';
    }
}
