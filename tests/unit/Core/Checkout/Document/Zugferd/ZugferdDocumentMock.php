<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document\Zugferd;

use Shopware\Core\Checkout\Cart\Price\AmountCalculator;
use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Checkout\Document\Zugferd\ZugferdDocument;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
class ZugferdDocumentMock extends ZugferdDocument
{
    public function getDomContent(OrderEntity $order, ?AmountCalculator $calculator): \DOMDocument
    {
        try {
            $this->getContent($order, $calculator);
        } catch (DocumentException) {
        }

        return $this->zugferdBuilder->getContentAsDomDocument();
    }
}
