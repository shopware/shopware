<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\FileGenerator;

use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Framework\Deprecation\BCChange\ExperimentalReplacement;
use Shopware\Core\Framework\Log\Package;

#[Package('after-sales')]
#[ExperimentalReplacement(
    version: 'v6.9.0',
    feature: 'DOCUMENT_GENERATION_REWORK',
    replacement: DocumentFormat::class,
)]
class FileTypes
{
    final public const PDF = 'pdf';
    final public const XML = 'xml';

    final public const PDF_CONTENT_TYPE = 'application/pdf';
    final public const XML_CONTENT_TYPE = 'application/xml';
}
