<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Category\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryService;
use Shopware\Core\Content\Category\Event\NavigationRouteValidateEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Content\Category\Subscriber\NavigationRouteValidateSubscriber;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(NavigationRouteValidateSubscriber::class)]
class NavigationRouteValidateSubscriberTest extends TestCase
{
    const INVALID_SALESCHANNEL_CATEGORY_ID = '345';
    const VALID_SALESCHANNEL_CATEGORY_ID = '123';

    private NavigationRouteValidateSubscriber $subscriber;
    private SalesChannelContext $salesChannelContextMock;

    public function setUp(): void
    {
        $connectionMock = $this->createMock(Connection::class);
        $categoryRepositoryMock = $this->createMock(SalesChannelRepository::class);
        $categoryService = new CategoryService($connectionMock, $categoryRepositoryMock);

        $salesChannelMock = $this->createMock(SalesChannelEntity::class);
        $salesChannelMock->method('getNavigationCategoryId')->willReturn(self::VALID_SALESCHANNEL_CATEGORY_ID);

        $salesChannelContextMock = $this->createMock(SalesChannelContext::class);
        $salesChannelContextMock->method('getSalesChannel')->willReturn($salesChannelMock);

        $this->salesChannelContextMock = $salesChannelContextMock;        
        $this->subscriber = new NavigationRouteValidateSubscriber($categoryService);
    }

    public function testHasEvents(): void
    {
        $expectedEvents = [
            NavigationRouteValidateEvent::class => 'validate',
        ];

        static::assertEquals($expectedEvents, NavigationRouteValidateSubscriber::getSubscribedEvents());
    }

    public function testValidateEventWhenValid(): void
    {
        $navigationRouteValidateEvent = new NavigationRouteValidateEvent(
            self::VALID_SALESCHANNEL_CATEGORY_ID, 
            '',
            $this->salesChannelContextMock
        );

        $this->subscriber->validate($navigationRouteValidateEvent);
    
        static::assertTrue($navigationRouteValidateEvent->isValid());
    }

    public function testValidateEventWhenInValid(): void
    {
        $navigationRouteValidateEvent = new NavigationRouteValidateEvent(
            self::INVALID_SALESCHANNEL_CATEGORY_ID, 
            '',
            $this->salesChannelContextMock
        );

        $this->subscriber->validate($navigationRouteValidateEvent);

        static::assertFalse($navigationRouteValidateEvent->isValid());
    }
}
