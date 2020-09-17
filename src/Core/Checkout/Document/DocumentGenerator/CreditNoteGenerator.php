<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\DocumentGenerator;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

class CreditNoteGenerator extends AbstractOrderDocumentGenerator implements DocumentGeneratorInterface
{
    public const CREDIT_NOTE = 'credit_note';
    public const DEFAULT_TEMPLATE = '@Framework/documents/credit_note.html.twig';

    public function supports(string $documentType): bool
    {
        return $documentType === self::CREDIT_NOTE;
    }

    protected function getExtraParameters(OrderEntity $order, Context $context): array
    {
        $lineItems = $order->getLineItems();
        $creditItems = [];
        if ($lineItems) {
            foreach ($lineItems as $lineItem) {
                if ($lineItem->getType() === LineItem::CREDIT_LINE_ITEM_TYPE) {
                    $creditItems[] = $lineItem;
                }
            }
        }

        return [
            'creditItems' => $creditItems,
        ];
    }

    protected function getDefaultTemplate(): string
    {
        return self::DEFAULT_TEMPLATE;
    }
}
