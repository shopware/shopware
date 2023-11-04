<?php declare(strict_types=1);

namespace Shopware\Administration\Snippet;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Cache\Adapter\AdapterInterface;

#[Package('administration')]
class CachedSnippetFinder implements SnippetFinderInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SnippetFinder $snippetFinder,
        private readonly AdapterInterface $cache
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function findSnippets(string $locale): array
    {
        $cacheKey = $this->getCacheKey($locale);
        $item = $this->cache->getItem($cacheKey);

        if ($item->isHit()) {
            return $item->get();
        }

        $snippets = $this->snippetFinder->findSnippets($locale);

        $item->set($snippets);
        $this->cache->save($item);

        return $snippets;
    }

    private function getCacheKey(string $locale): string
    {
        return 'admin_snippet_' . $locale;
    }
}
