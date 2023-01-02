<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\FileGenerator;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Checkout\Document\GeneratedDocument;

/**
 * @package customer-order
 */
#[Package('customer-order')]
interface FileGeneratorInterface
{
    public function supports(): string;

    public function generate(GeneratedDocument $html): string;

    public function getExtension(): string;

    public function getContentType(): string;
}
