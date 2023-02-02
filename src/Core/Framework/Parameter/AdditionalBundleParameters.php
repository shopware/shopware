<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Parameter;

use Composer\Autoload\ClassLoader;
use Shopware\Core\Framework\Plugin\KernelPluginCollection;

final class AdditionalBundleParameters
{
    /**
     * @var ClassLoader
     */
    private $classLoader;

    /**
     * @var KernelPluginCollection
     */
    private $pluginInstances;

    /**
     * @var array
     */
    private $kernelParameters;

    public function __construct(ClassLoader $classLoader, KernelPluginCollection $pluginInstances, array $kernelParameters)
    {
        $this->classLoader = $classLoader;
        $this->pluginInstances = $pluginInstances;
        $this->kernelParameters = $kernelParameters;
    }

    public function getClassLoader(): ClassLoader
    {
        return $this->classLoader;
    }

    public function setClassLoader(ClassLoader $classLoader): self
    {
        $this->classLoader = $classLoader;

        return $this;
    }

    public function getPluginInstances(): KernelPluginCollection
    {
        return $this->pluginInstances;
    }

    public function setPluginInstances(KernelPluginCollection $pluginInstances): self
    {
        $this->pluginInstances = $pluginInstances;

        return $this;
    }

    public function getKernelParameters(): array
    {
        return $this->kernelParameters;
    }

    public function setKernelParameters(array $kernelParameters): self
    {
        $this->kernelParameters = $kernelParameters;

        return $this;
    }
}
