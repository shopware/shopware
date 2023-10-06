<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig;

use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Log\Package;
use Symfony\Bundle\TwigBundle\TemplateIterator as TwigBundleIterator;

/**
 * @implements \IteratorAggregate<int, string>
 */
#[Package('core')]
class TemplateIterator implements \IteratorAggregate
{
    /**
     * @internal
     *
     * @param array<string, Bundle> $kernelBundles
     */
    public function __construct(
        private readonly TwigBundleIterator $templateIterator,
        private readonly array $kernelBundles
    ) {
    }

    public function getIterator(): \Traversable
    {
        $data = iterator_to_array($this->templateIterator, false);
        $search = [];
        $replace = [];

        foreach ($this->kernelBundles as $bundleName => $bundle) {
            $parents = class_parents($bundle);
            if (!isset($parents[Bundle::class])) {
                continue;
            }

            $search[] = sprintf('@%s/', $bundleName);
            $replace[] = '';
        }

        foreach ($data as &$template) {
            yield str_replace($search, $replace, $template);
        }
        unset($template);
    }
}
