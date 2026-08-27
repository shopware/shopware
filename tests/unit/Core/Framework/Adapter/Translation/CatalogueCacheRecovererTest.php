<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Translation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Translation\CatalogueCacheRecoverer;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Translation\Formatter\MessageFormatter;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\MessageCatalogueInterface;
use Symfony\Component\Translation\Translator as SymfonyTranslator;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CatalogueCacheRecoverer::class)]
class CatalogueCacheRecovererTest extends TestCase
{
    public function testIsBrokenCatalogueCacheErrorDetectsSymfonyReturnTypeFailure(): void
    {
        $recoverer = new CatalogueCacheRecoverer();

        static::assertTrue($recoverer->isBrokenCatalogueCacheError(new \TypeError(
            \sprintf('%s::getCatalogue(): Return value must be of type %s, false returned', SymfonyTranslator::class, MessageCatalogueInterface::class)
        )));
        static::assertFalse($recoverer->isBrokenCatalogueCacheError(new \TypeError('Unrelated type error')));
    }

    public function testRecoverDeletesCompiledCatalogueFiles(): void
    {
        $filesystem = new Filesystem();
        $kernelCacheDir = sys_get_temp_dir() . '/sw-catalogue-recoverer-' . uniqid('', true);
        $catalogueCacheDir = $kernelCacheDir . '/translations';
        $filesystem->mkdir($catalogueCacheDir);

        try {
            $translator = $this->createCachedSymfonyTranslator($catalogueCacheDir);
            $translator->getCatalogue('en-GB');

            $compiledFiles = (new Finder())->files()->name('catalogue.en-GB.*')->in($catalogueCacheDir);
            static::assertTrue($compiledFiles->hasResults());

            foreach ($compiledFiles as $file) {
                $filesystem->dumpFile($file->getPathname(), '<?php return false;');
            }

            $recoverer = new CatalogueCacheRecoverer($kernelCacheDir, $filesystem);
            $recoverer->recover($translator, 'en-GB');

            static::assertFalse((new Finder())->files()->name('catalogue.en-GB.*')->in($catalogueCacheDir)->hasResults());
            static::assertSame('Hello', $translator->getCatalogue('en-GB')->get('hello'));
        } finally {
            $filesystem->remove($kernelCacheDir);
        }
    }

    public function testRebuildWithoutCacheLoadsResourcesWhenCompiledFileStaysBroken(): void
    {
        $filesystem = new Filesystem();
        $catalogueCacheDir = sys_get_temp_dir() . '/sw-catalogue-rebuild-' . uniqid('', true);
        $filesystem->mkdir($catalogueCacheDir);

        try {
            $this->createCachedSymfonyTranslator($catalogueCacheDir)->getCatalogue('en-GB');

            foreach ((new Finder())->files()->name('catalogue.en-GB.*.php')->in($catalogueCacheDir) as $file) {
                $filesystem->dumpFile($file->getPathname(), '<?php return false;');
            }

            $translator = $this->createCachedSymfonyTranslator($catalogueCacheDir);
            $translator->setFallbackLocales($translator->getFallbackLocales());

            $catalogue = (new CatalogueCacheRecoverer())->rebuildWithoutCache($translator, 'en-GB');

            static::assertSame('Hello', $catalogue->get('hello'));
        } finally {
            $filesystem->remove($catalogueCacheDir);
        }
    }

    private function createCachedSymfonyTranslator(string $catalogueCacheDir): SymfonyTranslator
    {
        $translator = new SymfonyTranslator('en-GB', new MessageFormatter(), $catalogueCacheDir, debug: false);
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['hello' => 'Hello'], 'en-GB', 'messages');

        return $translator;
    }
}
