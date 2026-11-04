<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoAssertionInEventHookRule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 */
class Cases extends AbstractHookCase
{
    use AssertingHelperTrait;

    private EventHookDispatcher $hooks;

    private ?Event $seen = null;

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

    public function assertionsInAnInlineSubscriber(): void
    {
        $this->hooks->subscribe(new class implements EventSubscriberInterface {
            public static function getSubscribedEvents(): array
            {
                return [Event::class => 'onEvent'];
            }

            public function onEvent(Event $event): void
            {
                TestCase::assertInstanceOf(Event::class, $event);
            }
        });
    }

    public function assertionsInASubscriberHeldInAVariable(): void
    {
        $subscriber = new class implements EventSubscriberInterface {
            public static function getSubscribedEvents(): array
            {
                return [Event::class => 'onEvent'];
            }

            public function onEvent(Event $event): void
            {
                TestCase::assertNotNull($event);
            }
        };

        $this->hooks->subscribe($subscriber);
    }

    public function assertionsInASubscriberDeclaredElsewhere(): void
    {
        $this->hooks->subscribe(new AssertingSubscriberElsewhere());
    }

    public function capturingSubscriber(): void
    {
        $subscriber = new CapturingSubscriber();
        $this->hooks->subscribe($subscriber);

        static::assertNotNull($subscriber->seen);
    }

    public function assertionInAHelperTheHookCalls(): void
    {
        $this->hooks->on(Event::class, function (Event $event): void {
            $this->checkEventIsValid($event);
        });
    }

    public function assertionBehindTwoHelpers(): void
    {
        $this->hooks->on(Event::class, static function (Event $event): void {
            self::checkTwice($event);
        });
    }

    public function helperWithoutAssertion(): void
    {
        $this->hooks->on(Event::class, function (Event $event): void {
            $this->remember($event);
        });
    }

    public function assertionInAnInheritedHelper(): void
    {
        $this->hooks->on(Event::class, function (Event $event): void {
            $this->inheritedCheck($event);
        });
    }

    public function assertionInATraitHelper(): void
    {
        $this->hooks->on(Event::class, function (Event $event): void {
            $this->traitCheck($event);
        });
    }

    public function listenerOnAPlainDispatcher(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(Event::class, static function (): void {
            static::assertTrue(true);
        });
    }

    private function checkEventIsValid(Event $event): void
    {
        static::assertFalse($event->isPropagationStopped());
    }

    private static function checkTwice(Event $event): void
    {
        self::checkOnce($event);
        self::checkOnce($event);
    }

    private static function checkOnce(Event $event): void
    {
        static::assertNotNull($event);
    }

    private function remember(Event $event): void
    {
        $this->seen = $event;
    }
}
