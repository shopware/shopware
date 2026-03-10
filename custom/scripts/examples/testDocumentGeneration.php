<?php

namespace Scripts\Examples;

use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentGenerator;
use Shopware\Core\Checkout\DocumentV2\DocumentType;

require_once __DIR__ . '/base-script.php';

$env = 'prod'; // by default, kernel gets booted in dev

$kernel = require __DIR__ . '/../boot/boot.php';

class TestDocumentGeneration extends BaseScript
{
    public function run()
    {
        $documentGenerator = $this->getContainer()->get(DocumentGenerator::class);
        $documentGenerator->generate(DocumentType::Invoice->value, [DocumentFormat::EmbeddedZugferd->value]);
        //$documentGenerator->generate(DocumentType::Invoice->value, [DocumentFormat::Pdf->value, DocumentFormat::Html->value]);
    }
}


(new TestDocumentGeneration($kernel))->run();
