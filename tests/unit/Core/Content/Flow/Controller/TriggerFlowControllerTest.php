<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Controller\TriggerFlowController;
use Shopware\Core\Content\Flow\Exception\CustomTriggerByNameNotFoundException;
use Shopware\Core\Framework\App\Aggregate\FlowEvent\AppFlowEventCollection;
use Shopware\Core\Framework\App\Aggregate\FlowEvent\AppFlowEventEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(TriggerFlowController::class)]
class TriggerFlowControllerTest extends TestCase
{
    private TriggerFlowController $triggerFlowController;

    /**
     * @var StaticEntityRepository<AppFlowEventCollection>
     */
    private StaticEntityRepository $appFlowEventRepository;

    protected function setUp(): void
    {
        $appFlowEvent = new AppFlowEventEntity();
        $appFlowEvent->setUniqueIdentifier(Uuid::randomHex());
        $appFlowEvent->setAware(['customerId']);
        $appFlowEvent->setName('custom.checkout.event');

        $this->appFlowEventRepository = new StaticEntityRepository([
            new EntitySearchResult(
                'app_flow_event',
                1,
                new AppFlowEventCollection([$appFlowEvent]),
                null,
                new Criteria(),
                Context::createDefaultContext(),
            ),
        ]);

        $this->triggerFlowController = new TriggerFlowController(new EventDispatcher(), $this->appFlowEventRepository);
    }

    public function testTriggerWithWrongEventName(): void
    {
        $this->expectExceptionObject(new CustomTriggerByNameNotFoundException('custom.checkout.event'));

        $request = new Request();
        $request->setMethod('POST');
        $context = Context::createDefaultContext();
        $appFlowEventRepository = new StaticEntityRepository([
            new EntitySearchResult(
                'app_flow_event',
                1,
                new EntityCollection([]),
                null,
                new Criteria(),
                Context::createDefaultContext(),
            ),
        ]);

        $triggerFlowController = new TriggerFlowController(new EventDispatcher(), $appFlowEventRepository);
        $triggerFlowController->trigger('custom.checkout.event', $request, $context);
    }

    public function testTriggerWithInvalidAware(): void
    {
        $request = new Request();
        $request->setMethod('POST');
        $context = Context::createDefaultContext();

        $response = $this->triggerFlowController->trigger('custom.checkout.event', $request, $context);
        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());
        static::assertIsString($response->getContent());
        static::assertEquals('The trigger `custom.checkout.event`successfully dispatched!', json_decode($response->getContent(), true)['message']);
    }

    public function testTriggerWithValidAware(): void
    {
        $request = new Request();
        $context = Context::createDefaultContext();

        $response = $this->triggerFlowController->trigger('custom.checkout.event', $request, $context);
        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());
        static::assertIsString($response->getContent());
        static::assertEquals('The trigger `custom.checkout.event`successfully dispatched!', json_decode($response->getContent(), true)['message']);
    }
}
