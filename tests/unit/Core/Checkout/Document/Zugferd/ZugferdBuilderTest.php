<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document\Zugferd;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\Document\Zugferd\ZugferdBuilder;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(ZugferdBuilder::class)]
class ZugferdBuilderTest extends TestCase
{
    public function testUnsupportedTax(): void
    {
        $this->expectException(DocumentException::class);
        $this->expectExceptionMessage('Unsupported tax status');

        $builder = new ZugferdBuilder(
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EventDispatcherInterface::class)
        );

        $order = new OrderEntity();
        $order->setTaxStatus('random-tax');

        $builder->buildDocument($order, new DocumentGenerateOperation('order-id'), Context::createDefaultContext());
    }

    public function testBuildDocument(): void
    {
        static::markTestSkipped('Will be done in next MR');
    }
}
