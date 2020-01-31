<?php declare(strict_types=1);

namespace Shopware\Recovery\Common\DependencyInjection;

abstract class Container implements ContainerInterface
{
    /**
     * @var \Pimple\Container
     */
    protected $pimple;

    /**
     * @param array $config
     */
    public function __construct(\Pimple\Container $pimple, $config)
    {
        $this->pimple = $pimple;
        $this->pimple['config'] = $config;

        $this->setup($pimple);
    }

    abstract public function setup(\Pimple\Container $pimple);

    /**
     * Sets a service.
     *
     * @param string $id      The service identifier
     * @param object $service The service instance
     */
    public function set($id, $service): void
    {
        $this->pimple[$id] = $service;
    }

    /**
     * Gets a service.
     *
     * @param string $id              The service identifier
     * @param int    $invalidBehavior The behavior when the service does not exist
     *
     * @throws \InvalidArgumentException if the service is not defined
     *
     * @return object The associated service
     */
    public function get($id, $invalidBehavior = self::EXCEPTION_ON_INVALID_REFERENCE)
    {
        if ($this->pimple->offsetExists($id)) {
            return $this->pimple->offsetGet($id);
        }

        if ($invalidBehavior === self::EXCEPTION_ON_INVALID_REFERENCE) {
            throw new \InvalidArgumentException(sprintf('Identifier "%s" is not defined.', $id));
        }

        return null;
    }

    /**
     * Returns true if the given service is defined.
     *
     * @param string $id The service identifier
     *
     * @return bool true if the service is defined, false otherwise
     *
     * @api
     */
    public function has($id)
    {
        return $this->pimple->offsetExists($id);
    }

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
    public function getParameter($name)
    {
        $config = $this->pimple->offsetGet('config');

        if (!$this->hasParameter($name)) {
            throw new \InvalidArgumentException(sprintf('Parameter "%s" is not defined.', $name));
        }

        return $config[$name];
    }

    /**
     * Checks if a parameter exists.
     *
     * @param string $name The parameter name
     *
     * @return bool The presence of parameter in container
     *
     * @api
     */
    public function hasParameter($name)
    {
        $config = $this->pimple->offsetGet('config');

        return isset($config[$name]);
    }

    /**
     * Sets a parameter.
     *
     * @param string $name  The parameter name
     * @param mixed  $value The parameter value
     *
     * @api
     */
    public function setParameter($name, $value): void
    {
        $config = $this->pimple->offsetGet('config');
        $config[$name] = $value;

        $this->pimple->offsetSet('config', $config);
    }
}
