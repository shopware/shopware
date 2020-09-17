<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\DocumentGenerator;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

class InvoiceGenerator extends AbstractOrderDocumentGenerator implements DocumentGeneratorInterface
{
    public const INVOICE = 'invoice';
    public const DEFAULT_TEMPLATE = '@Framework/documents/invoice.html.twig';

    public function supports(string $documentType): bool
    {
        return $documentType === self::INVOICE;
    }

    protected function getExtraParameters(OrderEntity $order, Context $context): array
    {
        return [];
    }

    protected function getDefaultTemplate(): string
    {
        return self::DEFAULT_TEMPLATE;
    }
}
