<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Translation;

use Shopware\Core\Framework\Adapter\AdapterException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Translation\MessageCatalogueInterface;
use Symfony\Component\Translation\Translator as SymfonyTranslator;

/**
 * Rebuilds Symfony translation catalogues when a compiled catalogue include returns false.
 *
 * @internal
 */
#[Package('framework')]
class CatalogueCacheRecoverer
{
    public function __construct(
        private readonly ?string $cacheDir = null,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
    }

    public function isBrokenCatalogueCacheError(\TypeError $exception): bool
    {
        return str_contains($exception->getMessage(), 'getCatalogue()')
            && str_contains($exception->getMessage(), MessageCatalogueInterface::class);
    }

    public function recover(SymfonyTranslator $translator, ?string $locale): void
    {
        // Drop in-memory catalogues, including a possible `false` from a failed include().
        $translator->setFallbackLocales($translator->getFallbackLocales());

        $this->invalidateCompiledCatalogueCache($locale ?? $translator->getLocale());
    }

    public function rebuildWithoutCache(SymfonyTranslator $translator, ?string $locale): MessageCatalogueInterface
    {
        $locale ??= $translator->getLocale();

        $initializeCatalogue = new \ReflectionMethod(SymfonyTranslator::class, 'initializeCatalogue');
        $initializeCatalogue->invoke($translator, $locale);

        $cataloguesProperty = new \ReflectionProperty(SymfonyTranslator::class, 'catalogues');
        /** @var array<string, MessageCatalogueInterface|false|null> $catalogues */
        $catalogues = $cataloguesProperty->getValue($translator);
        $catalogue = $catalogues[$locale] ?? null;

        if (!$catalogue instanceof MessageCatalogueInterface) {
            throw AdapterException::invalidArgument('Failed to rebuild the translation catalogue without the compiled cache.');
        }

        return $catalogue;
    }

    private function invalidateCompiledCatalogueCache(string $locale): void
    {
        $catalogueCacheDir = $this->cacheDir !== null && $this->cacheDir !== ''
            ? $this->cacheDir . '/translations'
            : null;

        if ($catalogueCacheDir === null || !$this->filesystem->exists($catalogueCacheDir)) {
            return;
        }

        $finder = (new Finder())->files()->in($catalogueCacheDir)->name('catalogue.' . $locale . '.*');

        foreach ($finder as $file) {
            $path = $file->getPathname();
            if (\function_exists('opcache_invalidate')) {
                opcache_invalidate($path, true);
            }

            $this->filesystem->remove($path);
        }
    }
}
