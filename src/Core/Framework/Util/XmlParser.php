<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Util;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Config\Util\Exception\InvalidXmlException;
use Symfony\Component\Config\Util\Exception\XmlParsingException;
use Symfony\Component\Config\Util\XmlUtils;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Replacement for `XmlUtils::parse()` and `XmlUtils::loadFile()` for XML that may come from an extension.
 *
 * `XmlUtils` parses with `validateOnParse`, so libxml loads a document's DTD and resolves its entities
 * before Symfony rejects the document type. That lets a manifest read local files and send them out through PHP stream wrappers,
 * which `LIBXML_NONET` does not cover. This class rejects document types before `XmlUtils` sees the content.
 *
 * The messages of `XmlUtils` are kept, so callers that pass them on report the same errors as before.
 * Extensions that parse XML from an untrusted source should use this class instead of `XmlUtils` as well.
 */
#[Package('framework')]
final class XmlParser
{
    /**
     * @param string|callable|null $schemaOrCallable an XSD schema file path, a callable, or null to disable validation
     *
     * @throws UtilException
     * @throws XmlParsingException
     * @throws InvalidXmlException
     */
    public static function parse(string $content, string|callable|null $schemaOrCallable = null): \DOMDocument
    {
        self::assertNoDocumentType($content);

        return XmlUtils::parse($content, $schemaOrCallable);
    }

    /**
     * @param string|callable|null $schemaOrCallable an XSD schema file path, a callable, or null to disable validation
     *
     * @throws UtilException
     * @throws XmlParsingException
     */
    public static function loadFile(string $file, string|callable|null $schemaOrCallable = null): \DOMDocument
    {
        if (!is_file($file)) {
            throw UtilException::xmlParsingFailed(\sprintf('Resource "%s" is not a file.', $file));
        }

        if (!is_readable($file)) {
            throw UtilException::xmlParsingFailed(\sprintf('File "%s" is not readable.', $file));
        }

        // Read once, so the checked content is exactly the parsed content.
        $content = (new Filesystem())->readFile($file);

        if (trim($content) === '') {
            throw UtilException::xmlParsingFailed(\sprintf('File "%s" does not contain valid XML, it is empty.', $file));
        }

        try {
            return self::parse($content, $schemaOrCallable);
        } catch (InvalidXmlException $e) {
            throw UtilException::xmlParsingFailed(\sprintf('The XML file "%s" is not valid.', $file), $e->getPrevious());
        }
    }

    private static function assertNoDocumentType(string $content): void
    {
        if ($content === '') {
            // Keeps the `XmlUtils` behaviour for empty input; without a document there is no DTD to load.
            return;
        }

        $internalErrors = libxml_use_internal_errors(true);
        $entityLoader = libxml_get_external_entity_loader();
        libxml_clear_errors();

        // Nothing may be fetched while looking for the document type: no external subset, no external entity.
        libxml_set_external_entity_loader(static fn (?string $publicId, string $systemId, array $context): null => null);

        try {
            $dom = new \DOMDocument();
            $loaded = $dom->loadXML($content, \LIBXML_NONET | \LIBXML_COMPACT);
            $errors = $loaded ? [] : self::collectErrors();
        } finally {
            libxml_set_external_entity_loader($entityLoader);
            libxml_clear_errors();
            libxml_use_internal_errors($internalErrors);
        }

        if (!$loaded) {
            throw UtilException::xmlParsingFailed(implode("\n", $errors));
        }

        if ($dom->doctype !== null) {
            throw UtilException::xmlParsingFailed('Document types are not allowed.');
        }
    }

    /**
     * Same format as `XmlUtils`, so messages for malformed XML keep their shape.
     *
     * @return list<string>
     */
    private static function collectErrors(): array
    {
        $errors = [];

        foreach (libxml_get_errors() as $error) {
            $errors[] = \sprintf(
                '[%s %s] %s (in %s - line %d, column %d)',
                $error->level === \LIBXML_ERR_WARNING ? 'WARNING' : 'ERROR',
                $error->code,
                trim($error->message),
                $error->file ?: 'n/a',
                $error->line,
                $error->column,
            );
        }

        return $errors;
    }
}
