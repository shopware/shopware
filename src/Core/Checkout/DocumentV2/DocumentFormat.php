<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2;

/**
 * Document formats implemented by shopware
 */
enum DocumentFormat: string
{
    /** Normal human-readable PDF */
    case Pdf = 'pdf';
    /** accessible HTML to conform A11Y standards */
    case Html = 'html';
    /** human-readable PDF with embedded ZUGFeRD XML */
    case EmbeddedZugferd = 'embedded_zugferd';
    /** ZUGFeRD XML file */
    case ZugferdXml = 'zugferd_xml';
}
