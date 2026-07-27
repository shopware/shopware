<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Event\Hooks;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\Event\Hooks\DocumentGenerationHook;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacadeHookFactory;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\Facade\SystemConfigFacadeHookFactory;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentGenerationHook::class)]
class DocumentGenerationHookTest extends TestCase
{
    public function testExposesGenerationData(): void
    {
        $orderId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $order = new OrderEntity();
        $order->setId($orderId);

        $hook = new DocumentGenerationHook(
            $order,
            DocumentType::INVOICE->value,
            '1001',
            [DocumentFormat::PDF->value],
            $context,
        );

        static::assertSame('document-generation', $hook->getName());
        static::assertSame($order, $hook->getOrder());
        static::assertSame(DocumentType::INVOICE->value, $hook->getDocumentType());
        static::assertSame('1001', $hook->getDocumentNumber());
        static::assertSame([DocumentFormat::PDF->value], $hook->getFormats());
        static::assertSame($context, $hook->getContext());
    }

    public function testScriptsCanExtendTheOrder(): void
    {
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());

        $hook = new DocumentGenerationHook(
            $order,
            DocumentType::INVOICE->value,
            '1001',
            [DocumentFormat::PDF->value],
            Context::createDefaultContext(),
        );

        $hook->getOrder()->addArrayExtension('my_app', ['foo' => 'bar']);

        static::assertTrue($order->hasExtension('my_app'));
    }
}
