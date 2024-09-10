<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\Subscriber;

use Shopware\Core\Framework\Adapter\Cache\Event\AddCacheTagEvent;
use Shopware\Core\Framework\Adapter\Cache\Event\HttpCacheStoreEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;
use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('core')]
class HttpCacheTagDataCollectorSubscriber extends AbstractDataCollector implements EventSubscriberInterface, LateDataCollectorInterface
{
    /**
     * @var array<string, mixed>
     */
    public static array $tags = [];

    public function __construct(private readonly RequestStack $stack)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AddCacheTagEvent::class => 'add',
        ];
    }

    public function store(HttpCacheStoreEvent $event): void
    {
        $uri = $this->uri($event->request);

        self::$tags[$uri] = $event->tags;
    }

    public function reset(): void
    {
    }

    /**
     * @return array<string, mixed>|Data
     */
    public function getData(): array|Data
    {
        return $this->data;
    }

    public function getTotal(): int
    {
        // @phpstan-ignore-next-line
        return array_sum(array_map('count', $this->data));
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
    }

    public function lateCollect(): void
    {
        $this->data = $this->buildTags();
    }

    public static function getTemplate(): string
    {
        return '@Profiling/Collector/http_cache_tags.html.twig';
    }

    public function add(AddCacheTagEvent $event): void
    {
        $caller = $this->getCaller();

        $uri = $this->uri($this->stack->getCurrentRequest());

        if (!isset(self::$tags[$uri])) {
            self::$tags[$uri] = [];
        }

        foreach ($event->tags as $tag) {
            if (!isset(self::$tags[$uri][$tag])) {
                self::$tags[$uri][$tag] = [];
            }

            if (!isset(self::$tags[$tag][$caller])) {
                self::$tags[$uri][$tag][$caller] = 0;
            }

            ++self::$tags[$uri][$tag][$caller];
        }
    }

    private function uri(?Request $request): string
    {
        if ($request === null) {
            return 'n/a';
        }

        return $request->getRequestUri();
    }

    private function getCaller(): string
    {
        $source = debug_backtrace();

        // remove this function, listener function and wrapped listener
        array_shift($source);
        array_shift($source);
        array_shift($source);
        foreach ($source as $index => $element) {
            /** @var class-string $class */
            $class = $element['class'] ?? '';

            $instance = new \ReflectionClass($class);
            // skip dispatcher chain
            if ($instance->implementsInterface(EventDispatcherInterface::class)) {
                continue;
            }

            $before = $source[$index + 1];

            return $this->implode($element) . ' | ' . $this->implode($before);
        }

        return 'n/a';
    }

    /**
     * @param array<string, mixed> $caller
     */
    private function implode(array $caller): string
    {
        if (!\array_key_exists('class', $caller)) {
            return 'n/a';
        }
        if (!\array_key_exists('function', $caller)) {
            return 'n/a';
        }
        $class = explode('\\', $caller['class']);
        $class = array_pop($class);

        return $class . '::' . $caller['function'];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTags(): array
    {
        $tags = self::$tags;

        if (!isset($tags['n/a'])) {
            return $tags;
        }

        $keys = array_keys($tags);

        if (\count($keys) <= 1) {
            return $tags;
        }

        $na = $tags['n/a'];
        unset($tags['n/a']);

        $second = $keys[1];

        foreach ($na as $caller => $count) {
            if (!isset($tags[$second][$caller])) {
                $tags[$second][$caller] = 0;
            }

            $tags[$second][$caller] += $count;
        }

        return $tags;
    }
}
