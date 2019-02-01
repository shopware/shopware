<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Twig;

use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Twig_Loader_Filesystem;

class TemplateFinder
{
    /**
     * @var array
     */
    private $directories;

    /**
     * @var Twig_Loader_Filesystem
     */
    private $loader;

    /**
     * @var array[]
     */
    private $queue = [];

    public function __construct(Twig_Loader_Filesystem $loader)
    {
        $this->loader = $loader;

        $defaults = [
            'Administration',
            'Shopware',
            'Storefront',
        ];

        $directories = [];

        foreach ($loader->getNamespaces() as $namespace) {
            if ($namespace[0] === '!' || $namespace === '__main__' || \in_array($namespace, $defaults, true)) {
                continue;
            }

            $directories[] = $namespace;
        }

        $directories = array_merge($directories, $defaults);

        $this->directories = $directories;
    }

    public function addBundle(BundleInterface $bundle): bool
    {
        $directory = $bundle->getPath() . '/Resources/views/';
        if (!file_exists($directory)) {
            return false;
        }

        array_unshift($this->directories, $bundle->getName());
        $this->loader->addPath($directory, $bundle->getName());

        return true;
    }

    public function getTemplateName(string $template): string
    {
        //remove static template inheritance prefix
        if (strpos($template, '@') !== 0) {
            return $template;
        }

        $template = explode('/', $template);
        array_shift($template);
        $template = implode('/', $template);

        return $template;
    }

    /**
     * @throws \Twig_Error_Loader
     */
    public function find(string $template, $wholeInheritance = false, $ignoreMissing = false): string
    {
        $template = ltrim($template, '@');

        $queue = [];
        if (!$wholeInheritance && array_key_exists($template, $this->queue)) {
            $queue = $this->queue[$template];
        }
        if (empty($queue) || $wholeInheritance === true) {
            $queue = $this->queue[$template] = $this->directories;
        }

        foreach ($queue as $index => $prefix) {
            $name = '@' . $prefix . '/' . $template;

            unset($this->queue[$template][$index]);
            if ($this->loader->exists($name)) {
                return $name;
            }
        }

        if ($ignoreMissing === true) {
            return $template;
        }
        throw new \Twig_Error_Loader(sprintf('Unable to load template "%s". (Looked into: %s)', $template, implode(', ', array_values($queue))));
    }
}
