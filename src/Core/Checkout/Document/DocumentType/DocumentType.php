<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\DocumentType;

use Shopware\Core\Checkout\Document\DocumentContext;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

interface DocumentType
{
    public function supports(): string;

    public function documentFormat(): string;

    public function generateFromTemplate(
        OrderEntity $order,
        DocumentContext $documentContext,
        Context $context,
        string $template
    ): string;
}
