<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Events;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Events\FlowSendMailActionEvent;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\DataBag;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(FlowSendMailActionEvent::class)]
class FlowSendMailActionEventTest extends TestCase
{
    public function testEventConstructorParameters(): void
    {
        $context = Context::createDefaultContext();
        $flow = new StorableFlow('foo', $context);

        $expectDataBag = new DataBag(['data' => 'data']);
        $mailTemplate = new MailTemplateEntity();

        $event = new FlowSendMailActionEvent($expectDataBag, $mailTemplate, $flow);

        static::assertSame($context, $event->getContext());
        static::assertSame($expectDataBag, $event->getDataBag());
        static::assertSame($mailTemplate, $event->getMailTemplate());
        static::assertSame($flow, $event->getStorableFlow());
    }
}
