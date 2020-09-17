<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\DocumentGenerator;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

class StornoGenerator extends AbstractOrderDocumentGenerator implements DocumentGeneratorInterface
{
    public const STORNO = 'storno';
    public const DEFAULT_TEMPLATE = '@Framework/documents/storno.html.twig';

    public function supports(string $documentType): bool
    {
        return $documentType === self::STORNO;
    }

    protected function getExtraParameters(OrderEntity $order, Context $context): array
    {
        $this->negatePrices($order);

        return  [
            'order' => $order,
        ];
    }

    protected function getDefaultTemplate(): string
    {
        return self::DEFAULT_TEMPLATE;
    }

    private function negatePrices(OrderEntity $order)
    {
        foreach ($order->getLineItems() as $lineItem) {
            $lineItem->setUnitPrice(-1 * $lineItem->getUnitPrice());
            $lineItem->setTotalPrice(-1 * $lineItem->getTotalPrice());
        }
        foreach ($order->getPrice()->getCalculatedTaxes()->sortByTax()->getElements() as $tax) {
            $tax->setTax(-1 * $tax->getTax());
        }

        $order->setShippingTotal(-1 * $order->getShippingTotal());
        $order->setAmountNet(-1 * $order->getAmountNet());
        $order->setAmountTotal(-1 * $order->getAmountTotal());
    }
}
