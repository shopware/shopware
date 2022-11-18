<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script;

use Shopware\Core\Framework\Script\Debugging\ScriptTraces;

/**
 * @package core
 * This class is intended for auto completion in twig templates. So the developer can
 * set a doc block to get auto completion for all services.
 *
 * @example: {# @var services \Shopware\Core\Framework\Script\ServiceStubs #}
 *
 * @method \Shopware\Core\Checkout\Cart\Facade\CartFacade cart()
 * @method \Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacade repository()
 * @method \Shopware\Core\System\SystemConfig\Facade\SystemConfigFacade config()
 * @method \Shopware\Core\Framework\DataAbstractionLayer\Facade\SalesChannelRepositoryFacade store()
 * @method \Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryWriterFacade writer()
 * @method \Shopware\Core\Framework\Script\Api\ScriptResponseFactoryFacade response()
 * @method \Shopware\Core\Framework\Adapter\Cache\Script\Facade\CacheInvalidatorFacade cache()
 */
final class ServiceStubs
{
    private string $hook;

    private array $services = [];

    /**
     * @internal
     */
    public function __construct(string $hook)
    {
        $this->hook = $hook;
    }

    /**
     * @internal
     */
    public function __call(string $name, array $arguments): object
    {
        if (!isset($this->services[$name])) {
            throw new \RuntimeException(\sprintf('The service `%s` is not available in `%s`-hook', $name, $this->hook));
        }

        if (isset($this->services[$name]['deprecation'])) {
            ScriptTraces::addDeprecationNotice($this->services[$name]['deprecation']);
        }

        return $this->services[$name]['service'];
    }

    /**
     * @internal
     */
    public function add(string $name, object $service, ?string $deprecationNotice = null): void
    {
        if (isset($this->services[$name])) {
            throw new \RuntimeException(\sprintf('Service with name "%s" already exists', $name));
        }

        $this->services[$name]['service'] = $service;

        if ($deprecationNotice) {
            $this->services[$name]['deprecation'] = $deprecationNotice;
        }
    }

    /**
     * @internal
     */
    public function get(string $name): object
    {
        if (!isset($this->services[$name])) {
            throw new \RuntimeException(\sprintf('The service `%s` is not available in `%s`-hook', $name, $this->hook));
        }

        if (isset($this->services[$name]['deprecation'])) {
            ScriptTraces::addDeprecationNotice($this->services[$name]['deprecation']);
        }

        return $this->services[$name]['service'];
    }
}
