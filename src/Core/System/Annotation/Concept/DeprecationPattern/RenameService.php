<?php declare(strict_types=1);

namespace Shopware\Core\System\Annotation\Concept\DeprecationPattern;

use Doctrine\Common\Annotations\Annotation;

/**
 * @Annotation
 * @Target("CLASS")
 *
 * To rename a service that is registered in the DIC it is necessary to create a new Class with the new name of the service.
 * This class will be empty and just extends from the old service.
 * In the service registration for the DIC add a new ServiceDefinition for the new Class and mark the service with the old class name as an alias to the new Service.
 * Change all places where the old service was injected to the new service id.
 * Also deprecate the old service and link to the new Service in the deprecation annotation
 *
 * If we can remove the deprecation we have to copy the code over from the old to the new Service and can than delete the old service with it's service definition in the DIC.
 */
class RenameService
{
}
