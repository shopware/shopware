<?php declare(strict_types=1);

namespace Shopware\Framework\Twig;

use Shopware\Kernel;
use Symfony\Bundle\TwigBundle\Loader\FilesystemLoader;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

class TemplateFinder
{
    /**
     * @var array
     */
    private $directories = [];

    /**
     * @var FilesystemLoader
     */
    private $loader;

    /**
     * @var array[]
     */
    private $queue = [];

    private $defaultBundles = [
        'Administration',
        'Rest',
    ];

    /**
     * @param Kernel                  $kernel
     * @param \Twig_Loader_Filesystem $loader
     */
    public function __construct(Kernel $kernel, \Twig_Loader_Filesystem $loader)
    {
        $this->loader = $loader;

        foreach ($this->defaultBundles as $bundleName) {
            $bundlePath = $kernel->getBundle($bundleName)->getPath();
            $this->loader->addPath($bundlePath . '/Resources/views', $bundleName);
        }

        $namespaces = $this->loader->getNamespaces();

        foreach ($namespaces as $namespace) {
            if ($namespace[0] === '!' || $namespace === '__main__') {
                continue;
            }

            $this->directories[] = '@' . $namespace;
        }

        array_map([$this, 'addBundle'], $kernel::getPlugins()->getActivePlugins());
    }

    public function addBundle(BundleInterface $bundle): void
    {
        $directory = $bundle->getPath() . '/Resources/views/';
        if (!file_exists($directory)) {
            return;
        }

        $this->loader->addPath($directory, $bundle->getName());
        $this->directories[] = '@' . $bundle->getName();
    }

    /**
     * @throws \Twig_Error_Loader
     */
    public function find(string $template, $wholeInheritance = false): string
    {
        $queue = [];
        if (!$wholeInheritance && array_key_exists($template, $this->queue)) {
            $queue = $this->queue[$template];
        }
        if (empty($queue) || $wholeInheritance === true) {
            $queue = $this->queue[$template] = $this->directories;
        }

        foreach ($queue as $index => $prefix) {
            $name = $prefix . '/' . $template;

            unset($this->queue[$template][$index]);

            if ($this->loader->exists($name)) {
                return $name;
            }
        }

        throw new \Twig_Error_Loader(sprintf('Unable to load template "%s". (Looked into: %s)', $template, implode(', ', array_values($queue))));
    }
}
