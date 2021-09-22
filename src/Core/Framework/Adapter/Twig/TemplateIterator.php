<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig;

use Shopware\Core\Framework\Bundle;
use Symfony\Bundle\TwigBundle\TemplateIterator as TwigBundleIterator;

class TemplateIterator implements \IteratorAggregate
{
    private TwigBundleIterator $templateIterator;

    private array $kernelBundles;

    public function __construct(TwigBundleIterator $templateIterator, array $kernelBundles)
    {
        $this->templateIterator = $templateIterator;
        $this->kernelBundles = $kernelBundles;
    }

    /**
     * @deprecated tag:v6.5.0 - Return type will be changed to \Traversable
     */
    #[\ReturnTypeWillChange]
    public function getIterator(): iterable/* :\Traversable */
    {
        $data = iterator_to_array($this->templateIterator, false);
        $search = [];
        $replace = [];

        foreach ($this->kernelBundles as $bundleName => $bundle) {
            $parents = class_parents($bundle);
            if ($parents === false) {
                continue;
            }

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
