<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Twig;

use Shopware\Core\Framework\Bundle;
use Twig\Error\LoaderError;
use Twig\Loader\FilesystemLoader;

class TemplateFinder
{
    /**
     * @var array
     */
    private $bundles;

    /**
     * @var FilesystemLoader
     */
    private $loader;

    /**
     * @var array[]
     */
    private $queue = [];

    public function __construct(FilesystemLoader $loader)
    {
        $this->loader = $loader;

        $bundles = [];

        foreach ($loader->getNamespaces() as $namespace) {
            if ($namespace[0] === '!' || $namespace === '__main__') {
                continue;
            }

            $bundles[] = $namespace;
        }

        $this->bundles = $bundles;
    }

    public function addBundle(Bundle $bundle): void
    {
        $bundlePath = $bundle->getPath();
        $bundles = $this->bundles;

        foreach ($bundle->getViewPaths() as $directory) {
            $directory = $bundlePath . '/' . ltrim($directory, '/');
            if (!file_exists($directory)) {
                continue;
            }

            array_unshift($bundles, $bundle->getName());
            $this->loader->addPath($directory, $bundle->getName());
            $this->loader->addPath($directory);
        }

        $this->bundles = array_unique($bundles);
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
     * @throws LoaderError
     */
    public function find(string $template, $ignoreMissing = false, ?string $startAt = null): string
    {
        $template = ltrim($template, '@');

        $queue = $this->bundles;

        if ($startAt) {
            $index = array_search($startAt, $this->bundles, true);

            $queue = array_merge(
                array_slice($this->bundles, $index + 1),
                array_slice($this->bundles, 0, $index + 1)
            );
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
        throw new LoaderError(sprintf('Unable to load template "%s". (Looked into: %s)', $template, implode(', ', array_values($queue))));
    }
}
