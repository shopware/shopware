<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\FileGenerator;

use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Framework\Log\Package;

#[Package('customer-order')]
interface FileGeneratorInterface
{
    public function supports(): string;

    public function generate(RenderedDocument $html): string;

    public function getExtension(): string;

    public function getContentType(): string;
}
