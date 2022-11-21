<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Api\FlowActionCollector;
use Shopware\Core\Content\Flow\Api\FlowActionCollectorResponse;
use Shopware\Core\Content\Flow\Api\FlowActionDefinition;
use Shopware\Core\Content\Flow\Dispatching\Action\AddCustomerTagAction;
use Shopware\Core\Content\Flow\Dispatching\Action\RemoveOrderTagAction;
use Shopware\Core\Framework\App\Aggregate\FlowAction\AppFlowActionEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Event\CustomerAware;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\Flow\Api\FlowActionCollector
 */
class FlowActionCollectorTest extends TestCase
{
    public function testCollect(): void
    {
        $addCustomerTag = new AddCustomerTagAction($this->createMock(EntityRepository::class));
        $removeOrderTag = new RemoveOrderTagAction($this->createMock(EntityRepository::class));

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(static::once())->method('dispatch');

        $appFlowActionRepo = $this->createMock(EntityRepository::class);
        $entitySearchResult = $this->createMock(EntitySearchResult::class);
        $entitySearchResult->expects(static::once())
            ->method('getEntities')
            ->willReturn(new EntityCollection([
                (new AppFlowActionEntity())->assign([
                    'id' => Uuid::randomHex(),
                    'name' => 'slack.app',
                    'requirements' => ['orderAware'],
                    'delayable' => false,
                ]),
            ]));

        $appFlowActionRepo->expects(static::once())
            ->method('search')
            ->willReturn($entitySearchResult);

        $flowActionCollector = new FlowActionCollector(
            [$addCustomerTag, $removeOrderTag],
            $eventDispatcher,
            $appFlowActionRepo
        );

        $result = $flowActionCollector->collect(Context::createDefaultContext());

        static::assertInstanceOf(FlowActionCollectorResponse::class, $result);
        static::assertIsArray($result->getElements());

        $customerRequirements = [];
        if (!Feature::isActive('v6.5.0.0')) {
            $customerRequirements[] = CustomerAware::class;
        }
        $customerRequirements[] = 'customerAware';

        $orderRequirements = [];
        if (!Feature::isActive('v6.5.0.0')) {
            $orderRequirements[] = OrderAware::class;
        }
        $orderRequirements[] = 'orderAware';

        static::assertEquals(
            [
                AddCustomerTagAction::getName() => new FlowActionDefinition(
                    AddCustomerTagAction::getName(),
                    $customerRequirements,
                    true
                ),
                RemoveOrderTagAction::getName() => new FlowActionDefinition(
                    RemoveOrderTagAction::getName(),
                    $orderRequirements,
                    true
                ),
                'slack.app' => new FlowActionDefinition(
                    'slack.app',
                    ['orderAware'],
                    false
                ),
            ],
            $result->getElements()
        );
    }
}
