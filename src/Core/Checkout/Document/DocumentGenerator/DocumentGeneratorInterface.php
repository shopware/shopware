<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\DocumentGenerator;

use Shopware\Core\Checkout\Document\DocumentConfiguration;
use Shopware\Core\Framework\Context;

interface DocumentGeneratorInterface
{
    public function supports(string $documentType): bool;

    public function generate(DocumentConfiguration $config, Context $context, ?string $templatePath = null): string;

    public function getFileName(DocumentConfiguration $config): string;
}
