<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\FileGenerator;

use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.9.0 reason:experimental-replacement - Will be removed. Use {@link \Shopware\Core\Checkout\DocumentV2\DocumentFormat} instead.
 */
#[Package('after-sales')]
class FileTypes
{
    final public const PDF = 'pdf';
    final public const XML = 'xml';

    final public const PDF_CONTENT_TYPE = 'application/pdf';
    final public const XML_CONTENT_TYPE = 'application/xml';
}
