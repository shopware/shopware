<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Shared\MailFlow\DataProvider;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\OrderProvider;
use Shopware\Core\Content\Shared\MailFlow\Event\MailFlowDataCriteriaEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('after-sales')]
class OrderProviderTest extends TestCase
{
    public function testCanGetRepository(): void
    {
        $repository = $this->createMock(EntityRepository::class);

        $container = $this->createMock(ContainerInterface::class);
        $container
            ->expects($this->once())
            ->method('get')
            ->with(OrderDefinition::ENTITY_NAME . '.repository')
            ->willReturn($repository);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $provider = new OrderProvider($eventDispatcher, $container);

        $context = Context::createDefaultContext();

        // Trigger repository usage via getData()
        $repository
            ->expects($this->once())
            ->method('search');

        $provider->getData('some-id', $context);
    }

    public function testDispatchesCriteriaEvent(): void
    {
        $repository = $this->createMock(EntityRepository::class);

        $container = $this->createMock(ContainerInterface::class);
        $container
            ->method('get')
            ->willReturn($repository);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                static::callback(function ($event) {
                    return $event instanceof MailFlowDataCriteriaEvent
                        && $event->getEntityName() === OrderDefinition::ENTITY_NAME;
                }),
                'mail-flow.data.order.criteria.event'
            );

        $provider = new OrderProvider($eventDispatcher, $container);

        $context = Context::createDefaultContext();

        $provider->getCriteria('some-id', $context);
    }
}
