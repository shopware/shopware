<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Context;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\ContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Event\ContextCreatedEvent;
use Shopware\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContextFactory::class)]
class ContextFactoryTest extends TestCase
{
    public function testGetContext(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('fetchAssociative')->willReturn([
            'sales_channel_default_language_id' => Uuid::randomBytes(),
            'sales_channel_currency_factor' => 1.0,
            'sales_channel_currency_id' => Uuid::randomBytes(),
            'sales_channel_language_ids' => Defaults::LANGUAGE_SYSTEM,
        ]);

        $eventDispatcher = new CollectingEventDispatcher();
        $context = (new ContextFactory($connection, $eventDispatcher))->getContext(Uuid::randomHex(), [
            SalesChannelContextService::LANGUAGE_ID => Defaults::LANGUAGE_SYSTEM,
            SalesChannelContextService::CURRENCY_ID => Uuid::randomHex(),
            SalesChannelContextService::COUNTRY_ID => Uuid::randomHex(),
        ]);

        $events = $eventDispatcher->getEvents();
        static::assertCount(1, $events);
        static::assertInstanceOf(ContextCreatedEvent::class, $events[0]);

        static::assertSame(Defaults::LANGUAGE_SYSTEM, $context->getLanguageId());
    }
}
