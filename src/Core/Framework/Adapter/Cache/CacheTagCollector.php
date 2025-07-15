<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache;

use Shopware\Core\Framework\Adapter\Cache\Event\AddCacheTagEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('framework')]
#[AsEventListener]
class CacheTagCollector
{
    public const INVALID_URI = 'n/a';

    /**
     * @var array<string, array<string, bool>>
     */
    private array $tags = [];

    /**
     * @internal
     */
    public function __construct(
        private readonly RequestStack $stack,
        private readonly EventDispatcherInterface $dispatcher,
    ) {
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

    /**
     * @return list<string>
     */
    public function get(Request $request): array
    {
        $hash = self::uri($request);

        return array_keys($this->tags[$hash] ?? []);
    }

    /**
     * Collects cache tags for the current request, which will be used to tag the http cache entry.
     * This method will prevent adding the same tag multiple times and will not dispatch an event if only existing tags are provided.
     */
    public function addTag(string ...$tags): void
    {
        $hash = self::uri($this->stack->getCurrentRequest());

        $existingTags = $this->tags[$hash] ?? [];

        $tags = array_diff($tags, array_keys($existingTags));

        if (empty($tags)) {
            return;
        }

        $this->dispatcher->dispatch(new AddCacheTagEvent(...$tags));
    }

    public static function uri(?Request $request): string
    {
        if ($request === null) {
            return self::INVALID_URI;
        }

        if ($request->attributes->has('sw-original-request-uri')) {
            return (string) $request->attributes->get('sw-original-request-uri');
        }

        return $request->getRequestUri();
    }
}
