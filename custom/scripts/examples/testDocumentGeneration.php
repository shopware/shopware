<?php declare(strict_types=1);

namespace Scripts\Examples;

use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentGenerator;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Framework\Context;

require_once __DIR__ . '/base-script.php';

$env = 'prod'; // by default, kernel gets booted in dev

$kernel = require __DIR__ . '/../boot/boot.php';

class testDocumentGeneration extends BaseScript
{
    public function run(): void
    {
        $documentGenerator = $this->getContainer()->get(DocumentGenerator::class);
        $context = Context::createCLIContext();

        $orderId = '019cf72e720372baa50645ed166486ad';
        $orderVersionId = 'todo';

        $documentGenerator->generate($orderId, $orderVersionId, DocumentType::Invoice->value, [DocumentFormat::EmbeddedZugferd->value], $context);
        // $documentGenerator->generate($orderId, $orderVersionId, DocumentType::Invoice->value, [DocumentFormat::Pdf->value, DocumentFormat::Html->value], $context);
    }
}

(new TestDocumentGeneration($kernel))->run();
