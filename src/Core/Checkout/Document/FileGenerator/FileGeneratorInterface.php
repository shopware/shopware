<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\FileGenerator;

use Shopware\Core\Checkout\Document\DocumentGenerated;

interface FileGeneratorInterface
{
    public function supports(): string;

    public function generate(DocumentGenerated $html): string;

    public function getExtension(): string;
}
