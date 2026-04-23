<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentNumberGenerator;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerationContext;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentNumberGenerator::class)]
class DocumentNumberGeneratorTest extends TestCase
{
    public function testGenerateUsesDocumentNumberRangeTypeAndOrderSalesChannel(): void
    {
        $context = Context::createDefaultContext();
        $salesChannelId = Uuid::randomHex();
        $documentNumber = 'document-number';

        $generationContext = new DocumentGenerationContext(
            orderId: Uuid::randomHex(),
            orderVersionId: Uuid::randomHex(),
            documentType: DocumentType::INVOICE->value,
            formats: [DocumentFormat::PDF->value],
            apiContext: $context,
        );

        $order = new OrderEntity();
        $order->setSalesChannelId($salesChannelId);

        $numberRangeValueGenerator = $this->createMock(NumberRangeValueGeneratorInterface::class);
        $numberRangeValueGenerator
            ->expects($this->once())
            ->method('getValue')
            ->with(
                DocumentNumberGenerator::NUMBER_RANGE_DOCUMENT_TYPE_PREFIX . DocumentType::INVOICE->value,
                $context,
                $salesChannelId,
                false,
            )
            ->willReturn($documentNumber);

        $generator = new DocumentNumberGenerator($numberRangeValueGenerator);

        static::assertSame($documentNumber, $generator->generate($generationContext, $order));
    }
}
