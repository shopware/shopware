<?php declare(strict_types=1);

namespace Shopware\Recovery\Common\DependencyInjection;

interface ContainerInterface
{
    public const EXCEPTION_ON_INVALID_REFERENCE = 1;

    /**
     * Sets a service.
     *
     * @param string $id      The service identifier
     * @param object $service The service instance
     *
     * @api
     */
    public function set($id, $service);

    /**
     * Gets a service.
     *
     * @param string $id              The service identifier
     * @param int    $invalidBehavior The behavior when the service does not exist
     *
     * @throws \InvalidArgumentException          if the service is not defined
     * @throws \ServiceCircularReferenceException When a circular reference is detected
     * @throws \ServiceNotFoundException          When the service is not defined
     *
     * @return object The associated service
     *
     * @see Reference
     *
     * @api
     */
    public function get($id, $invalidBehavior = self::EXCEPTION_ON_INVALID_REFERENCE);

    /**
     * Returns true if the given service is defined.
     *
     * @param string $id The service identifier
     *
     * @return bool true if the service is defined, false otherwise
     *
     * @api
     */
    public function has($id);

    /**
     * Gets a parameter.
     *
     * @param string $name The parameter name
     *
     * @throws \InvalidArgumentException if the parameter is not defined
     *
     * @return mixed The parameter value
     *
     * @api
     */
    public function getParameter($name);

    /**
     * Checks if a parameter exists.
     *
     * @param string $name The parameter name
     *
     * @return bool The presence of parameter in container
     *
     * @api
     */
    public function hasParameter($name);

    /**
     * Sets a parameter.
     *
     * @param string $name  The parameter name
     * @param mixed  $value The parameter value
     *
     * @api
     */
    public function setParameter($name, $value);
}
