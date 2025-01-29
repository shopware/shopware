<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document\Zugferd;

use horstoeko\zugferd\ZugferdDocumentBuilder;
use horstoeko\zugferd\ZugferdProfiles;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Checkout\Document\Zugferd\ZugferdDocument;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(ZugferdDocument::class)]
class ZugferdDocumentTest extends TestCase
{
    public function testViolations(): void
    {
        $this->expectException(DocumentException::class);
        $this->expectExceptionMessageMatches('/Unable to generate document. ([0-9]+) violation\(s\) found/');

        $order = new OrderEntity();
        $order->setAmountTotal(0.0);
        $order->setAmountNet(0.0);

        (new ZugferdDocument(ZugferdDocumentBuilder::createNew(ZugferdProfiles::PROFILE_XRECHNUNG_3)))->getContent($order);
    }

    public function testWithNegativePrice(): void
    {
        $this->expectException(DocumentException::class);
        $this->expectExceptionMessage('Price can\'t be negative or null: Test Item');

        $lineItem = new OrderLineItemEntity();
        $lineItem->setLabel('Test Item');
        $lineItem->setUnitPrice(-10);
        $lineItem->setTotalPrice(-10);

        $lineItem->setPrice(new CalculatedPrice(
            $lineItem->getUnitPrice(),
            $lineItem->getTotalPrice(),
            new CalculatedTaxCollection(),
            new TaxRuleCollection()
        ));

        (new ZugferdDocument(ZugferdDocumentBuilder::createNew(ZugferdProfiles::PROFILE_XRECHNUNG_3)))->withProductLineItem($lineItem, '');
    }
}
