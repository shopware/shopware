<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Twig;

use Shopware\Core\Framework\Bundle;
use Shopware\Core\Kernel;
use Twig\Cache\FilesystemCache;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Loader\FilesystemLoader;

class TemplateFinder
{
    /**
     * @var Environment
     */
    protected $twig;

    /**
     * @var FilesystemLoader
     */
    protected $loader;

    /**
     * @var Kernel
     */
    protected $kernel;

    /**
     * @var array
     */
    private $bundles;

    /**
     * @var string
     */
    private $cacheDir;

    public function __construct(Environment $twig, FilesystemLoader $loader, Kernel $kernel)
    {
        $this->twig = $twig;
        $this->loader = $loader;
        $this->kernel = $kernel;
        $this->cacheDir = $kernel->getCacheDir() . '/twig';
        $this->addBundles($kernel);
    }

    public function addBundle(Bundle $bundle): void
    {
        $bundlePath = $bundle->getPath();
        $bundles = $this->bundles;

        $directory = $bundlePath . '/Resources/views';

        if (!file_exists($directory)) {
            return;
        }

        array_unshift($bundles, $bundle->getName());
        $this->loader->addPath($directory, $bundle->getName());
        $this->loader->addPath($directory);

        $this->bundles = array_values(array_unique($bundles));
    }

    public function getTemplateName(string $template): string
    {
        //remove static template inheritance prefix
        if (mb_strpos($template, '@') !== 0) {
            return $template;
        }

        $template = explode('/', $template);
        array_shift($template);
        $template = implode('/', $template);

        return $template;
    }

    /**
     * A custom template resolving function is needed to allow multi inheritance of template.
     * This function will check if any other bundle tries to extend the requested template and
     * returns the path to the extending template. Otherwise the original path will be returned.
     *
     * @param string      $template      Path of the requested template, ideally with @Bundle prefix
     * @param bool        $ignoreMissing If set to true no error is throw if the template is missing
     * @param string|null $source        Name of the bundle which triggered the search
     *
     * @throws LoaderError
     */
    public function find(string $template, $ignoreMissing = false, ?string $source = null): string
    {
        $templatePath = $this->getTemplateName($template);
        $originalTemplate = $source ? null : $template;

        $queue = $this->filterBundles($this->bundles);

        $this->defineCache($queue);

        if ($source) {
            $index = array_search($source, $queue, true);

            $queue = array_merge(
                array_slice($queue, $index + 1),
                array_slice($queue, 0, $index + 1)
            );
        }

        // iterate over all bundles but exclude the originally requested bundle
        // example: if @Storefront/storefront/index.html.twig is requested, all bundles except Storefront will be checked first
        foreach ($queue as $prefix) {
            $name = '@' . $prefix . '/' . $templatePath;

            if ($name === $originalTemplate) {
                continue;
            }

            if (!$this->loader->exists($name)) {
                continue;
            }

            return $name;
        }

        // if no other bundle extends the requested template, load the original template
        if ($this->loader->exists($originalTemplate)) {
            return $originalTemplate;
        }

        if ($ignoreMissing === true) {
            return $templatePath;
        }

        throw new LoaderError(sprintf('Unable to load template "%s". (Looked into: %s)', $templatePath, implode(', ', array_values($queue))));
    }

    protected function filterBundles(array $bundles)
    {
        return $bundles;
    }

    protected function addBundles(Kernel $kernel): void
    {
        $bundles = [];

        foreach ($this->loader->getNamespaces() as $namespace) {
            if ($namespace[0] === '!' || $namespace === '__main__') {
                continue;
            }

            $bundles[] = $namespace;
        }

        $this->bundles = $bundles;

        $kernelBundles = $kernel->getBundles();

        foreach ($kernelBundles as $bundle) {
            if ($bundle instanceof Bundle) {
                $this->addBundle($bundle);
            }
        }
    }

    protected function defineCache(array $queue): void
    {
        if ($this->twig->getCache(false) instanceof FilesystemCache) {
            $configHash = implode(':', $queue);

            $fileSystemCache = new ConfigurableFilesystemCache($this->cacheDir);
            $fileSystemCache->setConfigHash($configHash);
            // Set individual twig cache for different configurations
            $this->twig->setCache($fileSystemCache);
        }
    }
}
