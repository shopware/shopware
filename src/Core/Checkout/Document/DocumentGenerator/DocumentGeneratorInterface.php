<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\DocumentGenerator;

use Shopware\Core\Checkout\Document\DocumentConfiguration;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

/**
 * @deprecated tag:v6.5.0 - Will be removed - please extends AbstractDocumentRenderer instead
 */
interface DocumentGeneratorInterface
{
    public function supports(): string;

    public function generate(
        OrderEntity $order,
        DocumentConfiguration $config,
        Context $context,
        ?string $templatePath = null
    ): string;

    public function getFileName(DocumentConfiguration $config): string;
}
