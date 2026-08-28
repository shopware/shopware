<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
enum DocumentFormat: string
{
    case HTML = 'html';
    case PDF = 'pdf';
    case ZUGFERD_XML = 'zugferd_xml';
    case ZUGFERD_EMBEDDED_PDF = 'zugferd_embedded_pdf';

    public function fileExtension(): string
    {
        return match ($this) {
            self::HTML => 'html',
            self::PDF, self::ZUGFERD_EMBEDDED_PDF => 'pdf',
            self::ZUGFERD_XML => 'xml',
        };
    }

    public function mimeType(): string
    {
        return match ($this) {
            self::HTML => 'text/html',
            self::PDF, self::ZUGFERD_EMBEDDED_PDF => 'application/pdf',
            self::ZUGFERD_XML => 'application/xml',
        };
    }
}
