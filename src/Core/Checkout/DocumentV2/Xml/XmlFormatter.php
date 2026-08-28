<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Xml;

use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Framework\Log\Package;

/**
 * Reformats an XML string into a deterministic pretty-printed shape and rejects malformed
 * input. This DOM round-trip is the only runtime XML safety gate for Zugferd output.
 *
 * @internal
 */
#[Package('after-sales')]
final readonly class XmlFormatter
{
    private const XML_VERSION = '1.0';

    private const XML_ENCODING = 'UTF-8';

    public function format(string $xml): string
    {
        if ($xml === '') {
            throw DocumentV2Exception::malformedXml(
                1,
                ['input' => ['XML payload is empty']],
            );
        }

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $dom = new \DOMDocument(
                self::XML_VERSION,
                self::XML_ENCODING,
            );

            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = true;

            if ($dom->loadXML($xml) === false) {
                throw DocumentV2Exception::malformedXml(
                    \count(libxml_get_errors()),
                    $this->collectErrors(),
                );
            }

            // loadXML inherits the encoding from the inputs XML declaration when present;
            // force UTF-8 so the output declaration is deterministic regardless of input shape.
            $dom->encoding = self::XML_ENCODING;

            $output = $dom->saveXML();

            if ($output === false) {
                throw DocumentV2Exception::malformedXml(
                    1,
                    ['serialize' => ['DOMDocument::saveXML returned false']],
                );
            }

            return $output;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /**
     * @return array<string, list<string>>
     */
    private function collectErrors(): array
    {
        $bucket = [];

        foreach (libxml_get_errors() as $error) {
            $key = \sprintf('line:%d', $error->line);

            $bucket[$key][] = trim($error->message);
        }

        return $bucket;
    }
}
