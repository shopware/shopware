<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\FileGenerator;

use Shopware\Core\Checkout\Document\GeneratedDocument;

interface FileGeneratorInterface
{
    public function supports(): string;

    public function generate(GeneratedDocument $html): string;

    public function getExtension(): string;

    public function getContentType(): string;
}
