<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Newsletter\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Dispatching\Storer\ScalarValuesStorer;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientEntity;
use Shopware\Core\Content\Newsletter\Event\NewsletterRegisterEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(NewsletterRegisterEvent::class)]
class NewsletterRegisterEventTest extends TestCase
{
    public function testScalarValuesCorrectly(): void
    {
        $event = new NewsletterRegisterEvent(
            Context::createDefaultContext(),
            new NewsletterRecipientEntity(),
            'my-url',
            'my-sales-channel-id'
        );

        $storer = new ScalarValuesStorer();

        $stored = $storer->store($event, []);

        $flow = new StorableFlow('foo', Context::createDefaultContext(), $stored);

        $storer->restore($flow);

        static::assertArrayHasKey('url', $flow->data());
        static::assertEquals('my-url', $flow->data()['url']);
    }
}
