<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Test\Integration\Helper;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Events\FlowSendMailActionEvent;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Test\Integration\Helper\MailEventListener;

/**
 * @internal
 *
 * Integration test helpers are excluded from the coverage source, so this test covers nothing.
 */
#[Package('framework')]
#[CoversNothing]
class MailEventListenerTest extends TestCase
{
    public function testNothingSentWithoutEvents(): void
    {
        static::assertFalse((new MailEventListener(['type-id' => 'order_confirmation_mail']))->sent('order_confirmation_mail'));
    }

    public function testSentOnlyForTheTypeOfAnInvokedEvent(): void
    {
        $listener = new MailEventListener(['type-id' => 'order_confirmation_mail']);
        $listener($this->createEvent('type-id'));

        static::assertTrue($listener->sent('order_confirmation_mail'));
        static::assertFalse($listener->sent('other_mail'));
        $listener->assertSent('order_confirmation_mail');
    }

    #[DataProvider('unusedTypeProvider')]
    public function testSentIsFalseForTypesWithoutEvents(string $type): void
    {
        $listener = new MailEventListener(['type-id' => 'order_confirmation_mail']);
        $listener($this->createEvent('type-id'));

        static::assertFalse($listener->sent($type));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unusedTypeProvider(): iterable
    {
        yield 'empty string' => [''];
        yield 'zero string' => ['0'];
        yield 'unknown type' => ['unknown'];
    }

    public function testEventsWithoutTemplateTypeAreIgnored(): void
    {
        $listener = new MailEventListener([]);
        $listener($this->createEvent(null));

        static::assertSame([], $listener->get());
    }

    public function testGetReturnsTheEventsOfAType(): void
    {
        $listener = new MailEventListener(['type-id' => 'order_confirmation_mail']);
        $event = $this->createEvent('type-id');
        $listener($event);

        static::assertSame([$event], $listener->get('order_confirmation_mail'));
    }

    private function createEvent(?string $typeId): FlowSendMailActionEvent
    {
        $template = new MailTemplateEntity();
        $template->setMailTemplateTypeId($typeId);

        return new FlowSendMailActionEvent(
            new DataBag(),
            $template,
            new StorableFlow('test', Context::createDefaultContext())
        );
    }
}
