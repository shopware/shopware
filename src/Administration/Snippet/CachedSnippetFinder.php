<?php declare(strict_types=1);

namespace Shopware\Administration\Snippet;

use Symfony\Component\Cache\Adapter\AdapterInterface;

class CachedSnippetFinder implements SnippetFinderInterface
{
    /**
     * @var SnippetFinder
     */
    private $snippetFinder;

    /**
     * @var AdapterInterface
     */
    private $cache;

    /**
     * @internal
     */
    public function __construct(SnippetFinder $snippetFinder, AdapterInterface $cache)
    {
        $this->snippetFinder = $snippetFinder;
        $this->cache = $cache;
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
