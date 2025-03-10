<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent;
use Shopware\Core\Framework\Update\Event\UpdatePostFinishEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\Event\PreWebhooksDispatch;
use Shopware\Core\Framework\Webhook\Webhook;
use Shopware\Core\Service\Subscriber\WebhookManagerSubscriber;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[CoversClass(WebhookManagerSubscriber::class)]
class WebhookManagerSubscriberTest extends TestCase
{
    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
    }

    public function testDuplicateSystemUpdatesAreRemoved(): void
    {
        $subscriber = new WebhookManagerSubscriber();

        $event = new PreWebhooksDispatch([
            new Webhook(
                $this->ids->getBytes('wh-1'),
                'hook1',
                UpdatePostFinishEvent::EVENT_NAME,
                'https://test.com',
                false,
                $this->ids->getBytes('app-1'),
                'app1',
                true,
                '1.0.0',
                'secret',
                $this->ids->getBytes('role-1')
            ),
            // same update location
            new Webhook(
                $this->ids->getBytes('wh-2'),
                'hook2',
                UpdatePostFinishEvent::EVENT_NAME,
                'https://test.com',
                false,
                $this->ids->getBytes('app-1'),
                'app1',
                true,
                '1.0.0',
                'secret',
                $this->ids->getBytes('role-1')
            ),
            // different update location
            new Webhook(
                $this->ids->getBytes('wh-3'),
                'hook3',
                UpdatePostFinishEvent::EVENT_NAME,
                'https://test2.com',
                false,
                $this->ids->getBytes('app-1'),
                'app1',
                true,
                '1.0.0',
                'secret',
                $this->ids->getBytes('role-1')
            ),
            // different event
            new Webhook(
                $this->ids->getBytes('wh-4'),
                'hook4',
                CustomerBeforeLoginEvent::EVENT_NAME,
                'https://test2.com',
                false,
                $this->ids->getBytes('app-1'),
                'app1',
                true,
                '1.0.0',
                'secret',
                $this->ids->getBytes('role-1')
            ),
        ]);

        $subscriber->filterDuplicates($event);

        static::assertCount(3, $event->webhooks);
        static::assertEquals(
            [
                $this->ids->get('wh-4'),
                $this->ids->get('wh-2'),
                $this->ids->get('wh-3'),
            ],
            array_map(fn (Webhook $w) => Uuid::fromBytesToHex($w->id), $event->webhooks)
        );
    }
}
