<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\fixtures;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

class BundleFixture implements BundleInterface
{
    /**
     * @var string
     */
    private $path;

    /**
     * @var string
     */
    private $name;

    public function __construct(string $name, string $path)
    {
        $this->name = $name;
        $this->path = $path;
    }

    public function boot(): void
    {
    }

    public function shutdown(): void
    {
    }

    public function build(ContainerBuilder $container): void
    {
    }

    public function setContainer(ContainerInterface $container = null): void
    {
    }

    public function getContainerExtension(): ?ExtensionInterface
    {
        return null;
    }

    public function setName($name): void
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getNamespace(): string
    {
        return '';
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath($path): void
    {
        $this->path = $path;
    }
}
