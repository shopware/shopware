<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig;

use Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy\NamespaceHierarchyBuilder;
use Shopware\Core\Framework\Bundle;
use Twig\Cache\FilesystemCache;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Loader\LoaderInterface;

class TemplateFinder implements TemplateFinderInterface
{
    /**
     * @var Environment
     */
    protected $twig;

    /**
     * @var LoaderInterface
     */
    protected $loader;

    /**
     * @var array
     */
    protected $namespaceHierarchy;

    /**
     * @var string
     */
    protected $cacheDir;

    /**
     * @var NamespaceHierarchyBuilder
     */
    private $namespaceHierarchyBuilder;

    public function __construct(
        Environment $twig,
        LoaderInterface $loader,
        string $cacheDir,
        NamespaceHierarchyBuilder $namespaceHierarchyBuilder
    ) {
        $this->twig = $twig;
        $this->loader = $loader;
        $this->cacheDir = $cacheDir . '/twig';
        $this->namespaceHierarchyBuilder = $namespaceHierarchyBuilder;
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
     * {@inheritdoc}
     */
    public function find(string $template, $ignoreMissing = false, ?string $source = null): string
    {
        $templatePath = $this->getTemplateName($template);
        $originalTemplate = $source ? null : $template;

        $queue = $this->getNamespaceHierarchy();

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

    private function getNamespaceHierarchy(): array
    {
        if ($this->namespaceHierarchy) {
            return $this->namespaceHierarchy;
        }

        $namespaceHierarchy = array_unique($this->namespaceHierarchyBuilder->buildHierarchy());
        $this->defineCache($namespaceHierarchy);

        return $this->namespaceHierarchy = $namespaceHierarchy;
    }

    private function defineCache(array $queue): void
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
