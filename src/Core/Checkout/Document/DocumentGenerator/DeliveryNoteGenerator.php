<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\DocumentGenerator;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

class DeliveryNoteGenerator extends AbstractOrderDocumentGenerator implements DocumentGeneratorInterface
{
    public const DELIVERY_NOTE = 'delivery_note';
    public const DEFAULT_TEMPLATE = '@Framework/documents/delivery_note.html.twig';

    public function supports(string $documentType): bool
    {
        return $documentType === self::DELIVERY_NOTE;
    }

    protected function getExtraParameters(OrderEntity $order, Context $context): array
    {
        $delivery = null;
        if ($order->getDeliveries()) {
            $delivery = $order->getDeliveries()->first();
        }

        return [
            'orderDelivery' => $delivery,
        ];
    }

    protected function getDefaultTemplate(): string
    {
        return self::DEFAULT_TEMPLATE;
    }
}
