<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Dispatching\FlowState;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\FlowException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(StorableFlow::class)]
class StorableFlowTest extends TestCase
{
    private StorableFlow $storableFlow;

    protected function setUp(): void
    {
        $this->storableFlow = new StorableFlow('checkout.order.place', Context::createDefaultContext(), [], []);
        $this->storableFlow->setConfig(['config' => 'value']);
    }

    public function testGetName(): void
    {
        static::assertSame('checkout.order.place', $this->storableFlow->getName());
    }

    public function testGetContext(): void
    {
        static::assertEquals(Context::createDefaultContext(), $this->storableFlow->getContext());
    }

    public function testGetConfig(): void
    {
        static::assertSame(['config' => 'value'], $this->storableFlow->getConfig());
    }

    public function testGetFlowState(): void
    {
        static::expectException(FlowException::class);
        $this->storableFlow->getFlowState();

        $this->storableFlow->setFlowState(new FlowState());

        static::assertSame(new FlowState(), $this->storableFlow->getFlowState());
    }

    public function testStop(): void
    {
        static::expectException(FlowException::class);
        $this->storableFlow->stop();

        $this->storableFlow->setFlowState(new FlowState());
        $this->storableFlow->stop();
        static::assertTrue($this->storableFlow->getFlowState()->stop);
    }

    public function testStored(): void
    {
        static::assertSame([], $this->storableFlow->stored());
        static::assertNull($this->storableFlow->getStore('id'));

        $this->storableFlow->setStore('id', '123345');

        static::assertSame(['id' => '123345'], $this->storableFlow->stored());
        static::assertSame('123345', $this->storableFlow->getStore('id'));
    }

    public function testData(): void
    {
        static::assertSame([], $this->storableFlow->data());
        static::assertNull($this->storableFlow->getData('id'));

        $this->storableFlow->setData('id', '123345');

        static::assertSame(['id' => '123345'], $this->storableFlow->data());
        static::assertSame('123345', $this->storableFlow->getData('id'));

        $callback = fn () => 'Data';

        $this->storableFlow->setData('data', $callback);
        static::assertSame('Data', $this->storableFlow->getData('data'));
    }

    public function testLazy(): void
    {
        $callback = fn () => 'Order Data';

        $this->storableFlow->lazy('order', $callback);

        static::assertSame('Order Data', $this->storableFlow->getData('order'));
    }
}
