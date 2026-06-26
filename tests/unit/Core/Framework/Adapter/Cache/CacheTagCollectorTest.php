<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\Adapter\Cache\Event\AddCacheTagEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[CoversClass(CacheTagCollector::class)]
class CacheTagCollectorTest extends TestCase
{
    private EventDispatcher $dispatcher;

    private RequestStack $stack;

    private Request $request;

    private CacheTagCollector $collector;

    /**
     * @var list<AddCacheTagEvent>
     */
    private array $dispatched = [];

    protected function setUp(): void
    {
        $this->dispatcher = new EventDispatcher();
        $this->stack = new RequestStack();
        $this->request = Request::create('https://example.com/listing');
        $this->stack->push($this->request);

        $this->collector = new CacheTagCollector($this->stack, $this->dispatcher);

        // the collector itself collects via this event; register it like the kernel does
        $this->dispatcher->addListener(AddCacheTagEvent::class, $this->collector);
        // spy to count how often a *new* tag event is dispatched
        $this->dispatcher->addListener(AddCacheTagEvent::class, function (AddCacheTagEvent $event): void {
            $this->dispatched[] = $event;
        });
    }

    public function testTagsAreCollectedAndDeduplicated(): void
    {
        $this->collector->addTag('a', 'b');
        $this->collector->addTag('b', 'c');

        static::assertEqualsCanonicalizing(['a', 'b', 'c'], $this->collector->get($this->request));
    }

    public function testDuplicateTagsWithinASingleCallAreCollapsed(): void
    {
        $this->collector->addTag('a', 'a', 'b');

        static::assertEqualsCanonicalizing(['a', 'b'], $this->collector->get($this->request));
    }

    public function testNoEventIsDispatchedWhenOnlyExistingTagsAreAdded(): void
    {
        $this->collector->addTag('a', 'b');
        static::assertCount(1, $this->dispatched);

        // all tags already known -> must not dispatch again
        $this->collector->addTag('a', 'b');
        static::assertCount(1, $this->dispatched);

        // only the new tag must be dispatched, not the already known ones
        $this->collector->addTag('a', 'c');
        static::assertCount(2, $this->dispatched);
        static::assertSame(['c'], $this->dispatched[1]->tags);
    }

    public function testTagsAreScopedPerRequestUri(): void
    {
        $this->collector->addTag('a');

        $other = Request::create('https://example.com/other');
        $this->stack->push($other);
        $this->collector->addTag('b');

        static::assertSame(['b'], $this->collector->get($other));

        $this->stack->pop();
        static::assertSame(['a'], $this->collector->get($this->request));
    }

    public function testResetClearsCollectedTags(): void
    {
        $this->collector->addTag('a', 'b');
        $this->collector->reset();

        static::assertSame([], $this->collector->get($this->request));
    }
}
