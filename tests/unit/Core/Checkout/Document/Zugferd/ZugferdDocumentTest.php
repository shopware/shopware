<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document\Zugferd;

use horstoeko\zugferd\ZugferdDocumentBuilder;
use horstoeko\zugferd\ZugferdProfiles;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\DocumentConfiguration;
use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Checkout\Document\Zugferd\ZugferdDocument;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
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

        (new ZugferdDocument(ZugferdDocumentBuilder::createNew(ZugferdProfiles::PROFILE_XRECHNUNG_3), new DocumentConfiguration()))->getContent($order);
    }
}
