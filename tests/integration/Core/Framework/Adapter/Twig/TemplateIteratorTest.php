<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Adapter\Twig;

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

    private TemplateIterator $iterator;

    protected function setUp(): void
    {
        $this->iterator = static::getContainer()->get(TemplateIterator::class);
    }

    public function testIteratorDoesNotFullPath(): void
    {
        $templateList = iterator_to_array($this->iterator, false);
        $bundles = static::getContainer()->getParameter('kernel.bundles');
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

    public function testIteratorKeepsSymfonyDefaultDotFileBehavior(): void
    {
        $templateList = iterator_to_array($this->iterator, false);

        foreach ($templateList as $template) {
            static::assertStringNotContainsString('/.', $template);
        }
    }

    public function testFilteredLookupIncludesHiddenTemplatePathsWhenRequested(): void
    {
        $templateList = iterator_to_array($this->iterator->getTemplatePathsForSubPath('files/agentic', true), false);

        static::assertContains('files/agentic/llms.txt.twig', $templateList);
    }

    public function testFilteredLookupCanKeepDefaultDotFileBehavior(): void
    {
        $templateList = iterator_to_array($this->iterator->getTemplatePathsForSubPath('files/agentic'), false);

        static::assertContains('files/agentic/llms.txt.twig', $templateList);
    }
}
