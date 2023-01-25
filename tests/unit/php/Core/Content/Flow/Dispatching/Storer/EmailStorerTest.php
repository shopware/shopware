<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Storer;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent;
use Shopware\Core\Content\Flow\Dispatching\Aware\EmailAware;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Dispatching\Storer\EmailStorer;
use Shopware\Core\Content\Test\Flow\TestFlowBusinessEvent;

/**
 * @package business-ops
 *
 * @internal
 *
 * @covers \Shopware\Core\Content\Flow\Dispatching\Storer\EmailStorer
 */
class EmailStorerTest extends TestCase
{
    private EmailStorer $storer;

    public function setUp(): void
    {
        $this->storer = new EmailStorer();
    }

    public function testStore(): void
    {
        $event = $this->createMock(CustomerBeforeLoginEvent::class);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayHasKey(EmailAware::EMAIL, $stored);
    }

    /**
     * @dataProvider storableProvider
     */
    public function testRestore(bool $hasStore): void
    {
        $email = 'shopware-test@gmail.com';

        /** @var MockObject&StorableFlow $storable */
        $storable = $this->createMock(StorableFlow::class);

        if ($hasStore) {
            $storable->expects(static::exactly(1))
                ->method('hasStore')
                ->willReturn(true);

            $storable->expects(static::exactly(1))
                ->method('getStore')
                ->willReturn($email);

            $storable->expects(static::exactly(1))
                ->method('setData')
                ->with(EmailAware::EMAIL, $email);
        } else {
            $storable->expects(static::exactly(1))
                ->method('hasStore')
                ->willReturn(false);

            $storable->expects(static::never())
                ->method('getStore');

            $storable->expects(static::never())
                ->method('setData');
        }

        $this->storer->restore($storable);
    }

    public function storableProvider(): \Generator
    {
        yield 'Store key exists' => [
            true,
        ];

        yield 'Store key non exists' => [
            false,
        ];
    }

    public function awareProvider(): \Generator
    {
        $event = $this->createMock(CustomerBeforeLoginEvent::class);
        yield 'Store with Aware' => [
            $event,
            true,
        ];

        $event = $this->createMock(TestFlowBusinessEvent::class);
        yield 'Store with not Aware' => [
            $event,
            false,
        ];
    }
}
