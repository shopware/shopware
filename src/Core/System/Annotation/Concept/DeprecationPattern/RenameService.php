<?php declare(strict_types=1);

namespace Shopware\Core\System\Annotation\Concept\DeprecationPattern;

use Doctrine\Common\Annotations\Annotation;
use Shopware\Core\Framework\Log\Package;

/**
 * @Annotation
 *
 * @Target("CLASS")
 *
 * @DeprecationPattern
 *
 * To rename a service that is registered in the DIC it is necessary to create a new Class with the new name of the service.
 * This class will be empty and just extends from the old service.
 * In the service registration for the DIC add a new ServiceDefinition for the new Class and mark the service with the old class name as an alias to the new Service.
 * Change all places where the old service was injected to the new service id.
 * Also deprecate the old service and link to the new Service in the deprecation annotation
 *
 * If you can remove the deprecation you have to copy the code over from the old to the new Service and can than delete the old service with it's service definition in the DIC.
 */
#[Package('core')]
class RenameService
{
    public function __construct(array $info)
    {
        if (!\array_key_exists('deprecatedService', $info) || !\array_key_exists('replacedBy', $info)) {
            throw new \Exception('RenameService annotation must be created with a hint on the "deprecatedService" and the service it is "replacedBy".');
        }
    }
}
