<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Action;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Content\Flow\Dispatching\Action\ChangeCustomerGroupAction;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Event\CustomerAware;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(ChangeCustomerGroupAction::class)]
class ChangeCustomerGroupActionTest extends TestCase
{
    /**
     * @var Stub&EntityRepository<CustomerCollection>
     */
    private Stub&EntityRepository $repository;

    private ChangeCustomerGroupAction $action;

    protected function setUp(): void
    {
        $this->repository = static::createStub(EntityRepository::class);
        $this->action = new ChangeCustomerGroupAction($this->repository);
    }

    public function testRequirements(): void
    {
        static::assertSame(
            [CustomerAware::class],
            $this->action->requirements()
        );
    }

    public function testName(): void
    {
        static::assertSame('action.change.customer.group', ChangeCustomerGroupAction::getName());
    }

    public function testActionExecuted(): void
    {
        $groupId = Uuid::randomHex();
        $customerId = Uuid::randomHex();

        $flow = new StorableFlow('foo', Context::createDefaultContext(), [], [
            CustomerAware::CUSTOMER_ID => $customerId,
        ]);
        $flow->setConfig(['customerGroupId' => $groupId]);

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('update')
            ->with([['id' => $customerId, 'groupId' => $groupId, 'requestedGroupId' => null]]);

        $this->createAction($repository)->handleFlow($flow);
    }

    public function testActionWithNotAware(): void
    {
        $flow = new StorableFlow('foo', Context::createDefaultContext());

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->never())->method('update');

        $this->createAction($repository)->handleFlow($flow);
    }

    public function testActionWithEmptyConfig(): void
    {
        $flow = new StorableFlow('foo', Context::createDefaultContext(), [], [
            CustomerAware::CUSTOMER_ID => Uuid::randomHex(),
        ]);

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->never())->method('update');

        $this->createAction($repository)->handleFlow($flow);
    }

    /**
     * @param EntityRepository<CustomerCollection>|null $repository
     */
    private function createAction(?EntityRepository $repository = null): ChangeCustomerGroupAction
    {
        return new ChangeCustomerGroupAction($repository ?? $this->repository);
    }
}
