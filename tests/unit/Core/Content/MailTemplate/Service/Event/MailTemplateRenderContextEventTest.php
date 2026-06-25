<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\MailTemplate\Service\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\Service\Event\MailTemplateRenderContextEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

/**
 * @internal
 */
#[CoversClass(MailTemplateRenderContextEvent::class)]
class MailTemplateRenderContextEventTest extends TestCase
{
    public function testTemplateDataCanBeExtended(): void
    {
        $context = Context::createDefaultContext();
        $salesChannel = new SalesChannelEntity();

        $event = new MailTemplateRenderContextEvent(['foo' => 'bar'], $context, $salesChannel);
        $event->addTemplateData('baz', 'qux');

        static::assertSame(['foo' => 'bar', 'baz' => 'qux'], $event->getTemplateData());
        static::assertSame($context, $event->getContext());
        static::assertSame($salesChannel, $event->getSalesChannel());

        $event->setTemplateData(['updated' => true]);

        static::assertSame(['updated' => true], $event->getTemplateData());
    }
}
