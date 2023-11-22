<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Dispatching\Storer\ScalarValuesStorer;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[CoversClass(CustomerBeforeLoginEvent::class)]
class CustomerBeforeLoginEventTest extends TestCase
{
    public function testRestoreScalarValuesCorrectly(): void
    {
        $event = new CustomerBeforeLoginEvent(
            $this->createMock(SalesChannelContext::class),
            'my-email'
        );

        $storer = new ScalarValuesStorer();

        $stored = $storer->store($event, []);

        $flow = new StorableFlow('foo', Context::createDefaultContext(), $stored);

        $storer->restore($flow);

        static::assertArrayHasKey('email', $flow->data());
        static::assertEquals('my-email', $flow->data()['email']);
    }
}
