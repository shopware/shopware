<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache;

use Shopware\Core\Framework\Adapter\Cache\Event\AddCacheTagEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

#[AsEventListener]
class CacheTagCollector
{
    private array $tags = [];

    public function __construct(private readonly RequestStack $stack)
    {
    }

    public function __invoke(AddCacheTagEvent $event): void
    {
        $hash = $this->uri($this->stack->getCurrentRequest());

        foreach ($event->tags as $tag) {
            $this->tags[$hash][$tag] = true;
        }
    }

    public function reset(): void
    {
        $this->tags = [];
    }

    public function get(Request $request): array
    {
        $hash = $this->uri($request);

        if (!isset($this->tags[$hash])) {
            return [];
        }

        return array_keys($this->tags[$hash]);
    }

    private function uri(?Request $request): string
    {
        if ($request === null) {
            return 'n/a';
        }

        if ($request->attributes->has('sw-original-request-uri')) {
            return $request->attributes->get('sw-original-request-uri');
        }

        return $request->getRequestUri();
    }
}
