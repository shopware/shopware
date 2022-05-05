<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Adapter\Twig;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\TemplateIterator;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
class TemplateIteratorTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @var TemplateIterator
     */
    private $iterator;

    protected function setUp(): void
    {
        $this->iterator = $this->getContainer()->get(TemplateIterator::class);
    }

    public function testIteratorDoesNotFullPath(): void
    {
        $templateList = iterator_to_array($this->iterator, false);
        $bundles = $this->getContainer()->getParameter('kernel.bundles');
        $shopwareBundles = [];

        foreach ($bundles as $bundleName => $bundleClass) {
            if (isset(class_parents($bundleClass)[Bundle::class])) {
                $shopwareBundles[] = '@' . $bundleName . '/';
            }
        }

        foreach ($shopwareBundles as $shopwareBundle) {
            foreach ($templateList as $template) {
                static::assertStringNotContainsStringIgnoringCase($shopwareBundle, $template);
            }
        }
    }
}
