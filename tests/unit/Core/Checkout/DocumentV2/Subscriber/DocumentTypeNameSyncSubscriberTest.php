<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\DocumentV2\Subscriber\DocumentTypeNameSyncSubscriber;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentTypeNameSyncSubscriber::class)]
class DocumentTypeNameSyncSubscriberTest extends TestCase
{
    public function testSubscriberRemainsAvailableWhileMajorIsPending(): void
    {
        static::assertSame([EntityWriteEvent::class => 'writeTypeName'], DocumentTypeNameSyncSubscriber::getSubscribedEvents());
    }

    public function testNoSubscribersWhenRemovalFlagIsActive(): void
    {
        Feature::registerFeature('v6.9.0.0');
        Feature::setActive('v6.9.0.0', true);

        static::assertSame([], DocumentTypeNameSyncSubscriber::getSubscribedEvents());
    }

    /**
     * @deprecated tag:v6.9.0 - Remove with the major feature flag.
     */
    public function testDirectInvocationThrowsWhenRemovalFlagIsActive(): void
    {
        Feature::registerFeature('v6.9.0.0');
        Feature::setActive('v6.9.0.0', true);
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchOne');
        $subscriber = new DocumentTypeNameSyncSubscriber($connection);

        $this->expectException(FeatureException::class);
        $subscriber->writeTypeName(EntityWriteEvent::create(WriteContext::createFromContext(Context::createDefaultContext()), []));
    }
}
