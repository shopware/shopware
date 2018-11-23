<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\fixtures;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

class BundleFixture implements BundleInterface
{
    private $path;
    private $name;

    /**
     * BundleFixture constructor.
     *
     * @param $name string
     * @param $path string
     */
    public function __construct($name, $path)
    {
        $this->name = $name;
        $this->path = $path;
    }

    /**
     * Boots the Bundle.
     */
    public function boot()
    {
    }

    /**
     * Shutdowns the Bundle.
     */
    public function shutdown()
    {
    }

    /**
     * Builds the bundle.
     *
     * It is only ever called once when the cache is empty.
     */
    public function build(ContainerBuilder $container)
    {
    }

    /**
     * @param ContainerInterface|null $container
     */
    public function setContainer(ContainerInterface $container = null)
    {
    }

    /**
     * Returns the container extension that should be implicitly loaded.
     *
     * @return ExtensionInterface|null The default extension or null if there is none
     */
    public function getContainerExtension()
    {
    }

    /**
     * Returns the bundle name (the class short name).
     *
     * @param $name string
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * Returns the bundle name (the class short name).
     *
     * @return string The Bundle name
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Gets the Bundle namespace.
     *
     * @return string The Bundle namespace
     */
    public function getNamespace()
    {
    }

    /**
     * Gets the Bundle directory path.
     *
     * The path should always be returned as a Unix path (with /).
     *
     * @return string The Bundle absolute path
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Sets the Bundle directory path.
     *
     * @param $path string
     */
    public function setPath($path)
    {
        $this->path = $path;
    }
}
