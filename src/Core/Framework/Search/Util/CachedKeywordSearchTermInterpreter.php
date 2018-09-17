<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Search\Util;

use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\Search\Term\SearchPattern;

class CachedKeywordSearchTermInterpreter implements KeywordSearchTermInterpreterInterface
{
    /**
     * @var KeywordSearchTermInterpreterInterface
     */
    private $decorated;

    /**
     * @var CacheItemPoolInterface
     */
    private $cache;

    public function __construct(KeywordSearchTermInterpreterInterface $decorated, CacheItemPoolInterface $cache)
    {
        $this->decorated = $decorated;
        $this->cache = $cache;
    }

    public function interpret(string $word, string $scope, Context $context): SearchPattern
    {
        $key = md5(implode(' ', [$word, $scope, json_encode($context)]));

        $item = $this->cache->getItem($key);

        if ($item->isHit()) {
            try {
                return unserialize($item->get());
            } catch (\Throwable $e) {
            }
        }

        $pattern = $this->decorated->interpret($word, $scope, $context);

        $item->expiresAfter(new \DateInterval('PT5M'));

        $item->set(serialize($pattern));
        $this->cache->save($item);

        return $pattern;
    }
}
