<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig;

use Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy\NamespaceHierarchyBuilder;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\Service\ResetInterface;
use Twig\Cache\FilesystemCache;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Loader\LoaderInterface;

#[Package('core')]
class TemplateFinder implements TemplateFinderInterface, ResetInterface
{
    /**
     * @var string[]
     */
    private array $namespaceHierarchy = [];

    /**
     * @internal
     */
    public function __construct(
        private readonly Environment $twig,
        private readonly LoaderInterface $loader,
        private readonly string $cacheDir,
        private readonly NamespaceHierarchyBuilder $namespaceHierarchyBuilder
    ) {
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
        $sourcePath = $source ? $this->getTemplateName($source) : null;
        $sourceBundleName = $source ? $this->getSourceBundleName($source) : null;
        $originalTemplate = $source ? null : $template;

        $queue = $this->getNamespaceHierarchy();
        $modifiedQueue = $queue;

        // If we are trying to load the same file as the template, we do are not allowed to search the hierarchy
        // up to the source file as that has already been searched and that would lead to an endless template inheritance.

        if ($sourceBundleName !== null && $sourcePath === $templatePath) {
            $index = \array_search($sourceBundleName, $modifiedQueue, true);

            if (\is_int($index)) {
                $modifiedQueue = \array_merge(\array_slice($modifiedQueue, $index + 1), \array_slice($queue, 0, $index));
            }
        }

        // iterate over all bundles but exclude the originally requested bundle
        // example: if @Storefront/storefront/index.html.twig is requested, all bundles except Storefront will be checked first
        foreach ($modifiedQueue as $prefix) {
            $name = '@' . $prefix . '/' . $templatePath;

            // original template is loaded last
            if ($name === $originalTemplate) {
                continue;
            }

            if (!$this->loader->exists($name)) {
                continue;
            }

            return $name;
        }

        // Throw a useful error when the template cannot be found
        if ($originalTemplate === null) {
            if ($ignoreMissing === true) {
                return $templatePath;
            }

            throw new LoaderError(sprintf('Unable to load template "%s". (Looked into: %s)', $templatePath, implode(', ', array_values($modifiedQueue))));
        }

        // if no other bundle extends the requested template, load the original template
        if ($this->loader->exists($originalTemplate)) {
            return $originalTemplate;
        }

        if ($ignoreMissing === true) {
            return $templatePath;
        }

        throw new LoaderError(sprintf('Unable to load template "%s". (Looked into: %s)', $templatePath, implode(', ', array_values($modifiedQueue))));
    }

    public function reset(): void
    {
        $this->namespaceHierarchy = [];
    }

    private function getSourceBundleName(string $source): ?string
    {
        if (mb_strpos($source, '@') !== 0) {
            return null;
        }

        $source = explode('/', $source);
        $source = array_shift($source);
        $source = $source ? ltrim($source, '@') : null;

        return $source ?: null;
    }

    /**
     * @return string[]
     */
    private function getNamespaceHierarchy(): array
    {
        if ($this->namespaceHierarchy) {
            return $this->namespaceHierarchy;
        }

        $namespaceHierarchy = $this->namespaceHierarchyBuilder->buildHierarchy();
        $this->defineCache($namespaceHierarchy);

        return $this->namespaceHierarchy = array_keys($namespaceHierarchy);
    }

    /**
     * @param string[] $queue
     */
    private function defineCache(array $queue): void
    {
        if ($this->twig->getCache(false) instanceof FilesystemCache) {
            $configHash = md5((string) json_encode($queue, \JSON_THROW_ON_ERROR));

            $fileSystemCache = new ConfigurableFilesystemCache($this->cacheDir);
            $fileSystemCache->setConfigHash($configHash);
            // Set individual twig cache for different configurations
            $this->twig->setCache($fileSystemCache);
        }
    }
}
