<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\RuleFixture;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 */
class Cases extends TestCase
{
    private EventHookDispatcher $hooks;

    protected function setUp(): void
    {
        $this->hooks = new EventHookDispatcher(new EventDispatcher());
    }

    public function assertionsInsideTheHook(): void
    {
        $this->hooks->on(Event::class, function (Event $event): void {
            static::assertInstanceOf(Event::class, $event);
            $this->assertSame('name', $event::class);
        });
    }

    public function assertionInsideAnArrowFunction(): void
    {
        $this->hooks->on(Event::class, static fn (Event $event) => self::assertNotNull($event));
    }

    public function failInsideTheHook(): void
    {
        $this->hooks->on(Event::class, static function (): void {
            static::fail('must not be dispatched');
        });
    }

    public function captureThenAssert(): void
    {
        $seen = null;
        $this->hooks->on(Event::class, static function (Event $event) use (&$seen): void {
            $seen = $event;
        });

        static::assertNotNull($seen);
        static::assertInstanceOf(Event::class, $seen);
    }

    public function callableThatIsNotAClosure(): void
    {
        $this->hooks->on(Event::class, [$this, 'captureThenAssert']);
    }

    public function onOfAnotherObject(Other $other): void
    {
        $other->on(Event::class, static function (): void {
            static::assertTrue(true);
        });
    }

    public function listenerOnAPlainDispatcher(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(Event::class, static function (): void {
            static::assertTrue(true);
        });
    }
}

/**
 * @internal
 */
class Other
{
    public function on(string $eventName, callable $hook): void
    {
    }
}
