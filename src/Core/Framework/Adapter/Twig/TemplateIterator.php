<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig;

use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Log\Package;
use Symfony\Bundle\TwigBundle\TemplateIterator as TwigBundleIterator;
use Symfony\Component\Finder\Finder;

/**
 * @deprecated tag:v6.8.0 - reason:becomes-internal - Will be internal in v6.8.0
 */
#[Package('framework')]
class TemplateIterator implements TemplatePathIteratorInterface
{
    /**
     * @internal
     *
     * @param array<string, class-string> $kernelBundles
     * @param array<string, array{path?: string}> $kernelBundlesMetadata
     */
    public function __construct(
        private readonly TwigBundleIterator $templateIterator,
        private readonly array $kernelBundles,
        private readonly array $kernelBundlesMetadata,
    ) {
    }

    public function getIterator(): \Traversable
    {
        $search = [];
        $replace = [];

        foreach ($this->kernelBundles as $bundleName => $bundle) {
            $parents = class_parents($bundle);
            if (!isset($parents[Bundle::class])) {
                continue;
            }

            $search[] = \sprintf('@%s/', $bundleName);
            $replace[] = '';
        }

        foreach ($this->templateIterator as $template) {
            yield str_replace($search, $replace, $template);
        }
    }

    /**
     * Symfony's TemplateIterator exposes only full iteration and ignores dot paths through Finder's
     * default behavior. This mirrors Symfony's bundle template lookup for callers that need a
     * filtered sub tree, with an explicit opt-in for dot paths such as ".well-known".
     *
     * @return iterable<string>
     */
    public function getTemplatePathsForSubPath(string $subPath, bool $includeDotFiles = false): iterable
    {
        $subPath = trim($subPath, '/');
        if ($subPath === '') {
            return;
        }

        foreach ($this->kernelBundles as $bundleName => $bundleClass) {
            if (!isset(class_parents($bundleClass)[Bundle::class])) {
                continue;
            }

            $bundleMetadata = $this->kernelBundlesMetadata[$bundleName] ?? null;
            $bundlePath = $bundleMetadata['path'] ?? null;
            if (!\is_string($bundlePath)) {
                continue;
            }

            $templateDirectory = \is_dir($bundlePath . '/Resources/views') ? $bundlePath . '/Resources/views' : $bundlePath . '/templates';
            $directory = $templateDirectory . '/' . $subPath;
            if (!\is_dir($directory)) {
                continue;
            }

            $finder = new Finder();
            $finder
                ->files()
                ->followLinks()
                ->in($directory)
                ->name('*.twig')
                ->ignoreDotFiles(!$includeDotFiles)
                ->ignoreUnreadableDirs();

            foreach ($finder as $file) {
                yield $subPath . '/' . str_replace('\\', '/', $file->getRelativePathname());
            }
        }
    }
}
