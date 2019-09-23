<?php declare(strict_types=1);

namespace Shopware\Storefront\Test;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Twig\TemplateFinder;
use Shopware\Storefront\Test\fixtures\BundleFixture;
use Twig\Environment;

class TwigCacheTest extends TestCase
{
    use KernelTestBehaviour;

    public function testChangeCacehOndifferentPlugins(): void
    {
        /** @var Environment $twig */
        $twig = $this->getContainer()->get('twig');
        /** @var TemplateFinder $templateFinder */
        $templateFinder = $this->getContainer()->get(TemplateFinder::class);
        $bundleStorefront = new BundleFixture('Storefront', __DIR__ . '/fixtures/Storefront');
        $templateFinder->addBundle($bundleStorefront);
        $bundlePlugin1 = new BundleFixture('TestPlugin1', __DIR__ . '/fixtures/Plugins/TestPlugin1');
        $bundlePlugin2 = new BundleFixture('TestPlugin2', __DIR__ . '/fixtures/Plugins/TestPlugin2');
        $templateFinder->addBundle($bundlePlugin1);
        $templateName = 'frontend/index.html.twig';
        $templateFinder->find($templateName);

        $firstCacheKey = $twig->getCache(false)->generateKey($templateName, get_class($this));

        $templateFinder->addBundle($bundlePlugin2);
        $templateFinder->find($templateName);
        $secondCacheKey = $twig->getCache(false)->generateKey($templateName, get_class($this));

        static::assertNotEquals($firstCacheKey, $secondCacheKey);
    }
}
