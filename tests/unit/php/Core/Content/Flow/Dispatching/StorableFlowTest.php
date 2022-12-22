<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Dispatching\FlowState;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\FlowException;
use Shopware\Core\Framework\Context;

/**
 * @package business-ops
 *
 * @internal
 *
 * @covers \Shopware\Core\Content\Flow\Dispatching\StorableFlow
 */
class StorableFlowTest extends TestCase
{
    private StorableFlow $storableFlow;

    public function setUp(): void
    {
        $this->storableFlow = new StorableFlow('checkout.order.place', Context::createDefaultContext(), [], []);
        $this->storableFlow->setConfig(['config' => 'value']);
    }

    public function testGetName(): void
    {
        static::assertEquals('checkout.order.place', $this->storableFlow->getName());
    }

    public function testGetContext(): void
    {
        static::assertEquals(Context::createDefaultContext(), $this->storableFlow->getContext());
    }

    public function testGetConfig(): void
    {
        static::assertEquals(['config' => 'value'], $this->storableFlow->getConfig());
    }

    public function testGetFlowState(): void
    {
        static::expectException(FlowException::class);
        $this->storableFlow->getFlowState();

        $this->storableFlow->setFlowState(new FlowState());

        static::assertEquals(new FlowState(), $this->storableFlow->getFlowState());
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
        static::assertEquals([], $this->storableFlow->stored());
        static::assertNull($this->storableFlow->getStore('id'));

        $this->storableFlow->setStore('id', '123345');

        static::assertEquals(['id' => '123345'], $this->storableFlow->stored());
        static::assertEquals('123345', $this->storableFlow->getStore('id'));
    }

    public function testData(): void
    {
        static::assertEquals([], $this->storableFlow->data());
        static::assertNull($this->storableFlow->getData('id'));

        $this->storableFlow->setData('id', '123345');

        static::assertEquals(['id' => '123345'], $this->storableFlow->data());
        static::assertEquals('123345', $this->storableFlow->getData('id'));

        $callback = function () {
            return 'Data';
        };

        $this->storableFlow->setData('data', $callback);
        static::assertEquals('Data', $this->storableFlow->getData('data'));
    }

    public function testLazy(): void
    {
        $callback = function () {
            return 'Data';
        };

        $this->storableFlow->lazy('data', $callback, []);
        static::assertEquals('Data', $this->storableFlow->getData('data'));
    }
}
