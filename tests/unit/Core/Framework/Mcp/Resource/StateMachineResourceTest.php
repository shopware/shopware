<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Resource;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Resource\StateMachineResource;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionCollection;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionEntity;
use Shopware\Core\System\StateMachine\StateMachineCollection;
use Shopware\Core\System\StateMachine\StateMachineEntity;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StateMachineResource::class)]
class StateMachineResourceTest extends TestCase
{
    public function testReturnsFormattedStateMachines(): void
    {
        $openState = new StateMachineStateEntity();
        $openState->setId(Uuid::randomHex());
        $openState->setTechnicalName('open');
        $openState->setName('Open');

        $doneState = new StateMachineStateEntity();
        $doneState->setId(Uuid::randomHex());
        $doneState->setTechnicalName('done');
        $doneState->setName('Done');

        $transition = new StateMachineTransitionEntity();
        $transition->setId(Uuid::randomHex());
        $transition->setActionName('complete');
        $transition->setFromStateMachineState($openState);
        $transition->setToStateMachineState($doneState);

        $machine = new StateMachineEntity();
        $machine->setId(Uuid::randomHex());
        $machine->setTechnicalName('order.state');
        $machine->setName('Order State');
        $machine->setStates(new StateMachineStateCollection([$openState, $doneState]));
        $machine->setTransitions(new StateMachineTransitionCollection([$transition]));

        $collection = new StateMachineCollection([$machine]);
        $context = Context::createDefaultContext();

        $searchResult = new EntitySearchResult(
            'state_machine',
            1,
            $collection,
            null,
            new Criteria(),
            $context,
        );

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturn($searchResult);

        $resource = new StateMachineResource($repository);
        $result = ($resource)();

        static::assertSame('shopware://state-machines', $result['uri']);
        static::assertSame('application/json', $result['mimeType']);

        $data = json_decode($result['text'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertCount(1, $data);
        static::assertSame('order.state', $data[0]['technicalName']);
        static::assertCount(2, $data[0]['states']);
        static::assertCount(1, $data[0]['transitions']);
        static::assertSame('complete', $data[0]['transitions'][0]['actionName']);
        static::assertSame('open', $data[0]['transitions'][0]['fromState']);
        static::assertSame('done', $data[0]['transitions'][0]['toState']);
    }
}
