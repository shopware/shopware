<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\File;

use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

#[Package('discovery')]
class SvgContentValidator extends AbstractFileContentValidator
{
    private const SVG = 'svg';
    private const STYLE = 'style';
    private const ACTIVE_CONTENT_MESSAGE = 'SVG files with active content are not allowed.';
    private const INVALID_SVG_MESSAGE = 'The file is not a valid SVG document.';
    private const PARSE_ERROR_MESSAGE = 'The SVG file could not be parsed.';
    private const SVG_NAMESPACE = 'http://www.w3.org/2000/svg';
    private const XLINK_NAMESPACE = 'http://www.w3.org/1999/xlink';
    private const XMLNS_NAMESPACE = 'http://www.w3.org/2000/xmlns/';
    private const XML_NAMESPACE = 'http://www.w3.org/XML/1998/namespace';

    /**
     * @var list<string>
     */
    private readonly array $allowedElements;

    /**
     * @var list<string>
     */
    private readonly array $allowedAttributes;

    /**
     * @var list<string>
     */
    private readonly array $allowedReferenceAttributes;

    /**
     * @internal
     *
     * @param list<string> $allowedElements
     * @param list<string> $allowedAttributes
     * @param list<string> $allowedReferenceAttributes
     */
    public function __construct(
        array $allowedElements,
        array $allowedAttributes,
        array $allowedReferenceAttributes,
    ) {
        $this->allowedElements = $this->normalizeAllowlist($allowedElements);
        $this->allowedAttributes = $this->normalizeAllowlist($allowedAttributes);
        $this->allowedReferenceAttributes = $this->normalizeAllowlist($allowedReferenceAttributes);
    }

    public function getDecorated(): AbstractFileContentValidator
    {
        throw new DecorationPatternException(self::class);
    }

    public function supports(MediaFile $mediaFile): bool
    {
        return mb_strtolower($mediaFile->getFileExtension()) === self::SVG;
    }

    public function validate(MediaFile $mediaFile): void
    {
        if ($this->supports($mediaFile) === false) {
            return;
        }

        $previousErrorHandling = $this->captureLibxmlErrors();

        try {
            $reader = $this->openSvgReader($mediaFile);

            try {
                $this->validateDocument($reader);
            } finally {
                $reader->close();
            }

            if ($this->hasCollectedLibxmlErrors()) {
                throw MediaException::invalidFile(self::PARSE_ERROR_MESSAGE);
            }
        } finally {
            $this->restoreLibxmlErrorHandling($previousErrorHandling);
        }
    }

    private function validateDocument(\XMLReader $reader): void
    {
        $documentElementSeen = false;

        while ($reader->read()) {
            if ($this->isDisallowedNodeType($reader->nodeType)) {
                $this->rejectActiveContent();
            }

            if ($reader->nodeType !== \XMLReader::ELEMENT) {
                continue;
            }

            if ($documentElementSeen === false) {
                $this->validateRootElement($reader);
                $documentElementSeen = true;
            }

            $this->validateElement($reader);
        }

        if ($documentElementSeen === false) {
            throw MediaException::invalidFile(self::INVALID_SVG_MESSAGE);
        }
    }

    private function validateRootElement(\XMLReader $reader): void
    {
        $isSvgRoot = mb_strtolower($reader->localName) === self::SVG;
        $hasValidNamespace = $reader->namespaceURI === '' || $reader->namespaceURI === self::SVG_NAMESPACE;

        if ($isSvgRoot && $hasValidNamespace) {
            return;
        }

        throw MediaException::invalidFile(self::INVALID_SVG_MESSAGE);
    }

    private function validateElement(\XMLReader $reader): void
    {
        $elementName = mb_strtolower($reader->localName);

        $this->assertElementAllowed($elementName);
        $this->assertAttributesAllowed($reader);
        $this->assertStyleBodyAllowed($reader, $elementName);
    }

    private function assertElementAllowed(string $elementName): void
    {
        if (!\in_array($elementName, $this->allowedElements, true)) {
            $this->rejectActiveContent();
        }
    }

    private function assertAttributesAllowed(\XMLReader $reader): void
    {
        if (!$reader->hasAttributes) {
            return;
        }

        $attributePosition = $reader->moveToFirstAttribute();
        while ($attributePosition === true) {
            $this->assertAttributeAllowed($reader);
            $attributePosition = $reader->moveToNextAttribute();
        }

        $reader->moveToElement();
    }

    private function assertAttributeAllowed(\XMLReader $reader): void
    {
        $attributeName = mb_strtolower($reader->name);

        if ($this->isEventHandlerAttribute($attributeName)) {
            $this->rejectActiveContent();
        }

        if (!$this->isAllowedAttribute($reader, $attributeName)) {
            $this->rejectActiveContent();
        }

        $isReferenceAttribute = \in_array($attributeName, $this->allowedReferenceAttributes, true);
        if ($isReferenceAttribute && $this->isExternalReference($reader->value)) {
            $this->rejectActiveContent();
        }

        if ($this->containsExternalStyleReference($reader->value)) {
            $this->rejectActiveContent();
        }
    }

    private function isEventHandlerAttribute(string $attributeName): bool
    {
        return str_starts_with($attributeName, 'on');
    }

    private function assertStyleBodyAllowed(\XMLReader $reader, string $elementName): void
    {
        if ($elementName !== self::STYLE) {
            return;
        }

        if ($this->containsExternalStyleReference($reader->readInnerXml())) {
            $this->rejectActiveContent();
        }
    }

    private function rejectActiveContent(): never
    {
        throw MediaException::invalidFile(self::ACTIVE_CONTENT_MESSAGE);
    }

    /**
     * Route libxml errors into an internal buffer so `hasCollectedLibxmlErrors()`
     * can inspect them after parsing, and return the caller's previous setting
     * so it can be restored — libxml error handling is process-global state.
     */
    private function captureLibxmlErrors(): bool
    {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        return $previous;
    }

    private function restoreLibxmlErrorHandling(bool $previousValue): void
    {
        libxml_clear_errors();
        libxml_use_internal_errors($previousValue);
    }

    private function hasCollectedLibxmlErrors(): bool
    {
        return libxml_get_errors() !== [];
    }

    private function openSvgReader(MediaFile $mediaFile): \XMLReader
    {
        $reader = new \XMLReader();
        $opened = $reader->open($mediaFile->getFileName(), null, \LIBXML_NOERROR | \LIBXML_NOWARNING | \LIBXML_NONET);

        if ($opened !== true) {
            throw MediaException::invalidFile(self::PARSE_ERROR_MESSAGE);
        }

        return $reader;
    }

    private function isDisallowedNodeType(int $nodeType): bool
    {
        return \in_array($nodeType, [\XMLReader::DOC_TYPE, \XMLReader::ENTITY, \XMLReader::ENTITY_REF, \XMLReader::PI], true);
    }

    private function isExternalReference(string $value): bool
    {
        $value = trim($value);

        return $value !== '' && !str_starts_with($value, '#');
    }

    private function isAllowedAttribute(\XMLReader $reader, string $attributeName): bool
    {
        if (!\in_array($attributeName, $this->allowedAttributes, true)) {
            return false;
        }

        if ($reader->prefix === 'xmlns') {
            return $reader->namespaceURI === self::XMLNS_NAMESPACE && $reader->value === self::XLINK_NAMESPACE;
        }

        if ($reader->name === 'xmlns') {
            return $reader->namespaceURI === self::XMLNS_NAMESPACE && $reader->value === self::SVG_NAMESPACE;
        }

        if ($reader->prefix === 'xml') {
            return $reader->namespaceURI === self::XML_NAMESPACE;
        }

        if ($reader->prefix === 'xlink') {
            return $reader->namespaceURI === self::XLINK_NAMESPACE;
        }

        return $reader->namespaceURI === '' || $reader->namespaceURI === null;
    }

    private function containsExternalStyleReference(string $value): bool
    {
        if (preg_match_all('/url\(\s*([^)]+?)\s*\)/i', $value, $matches)) {
            foreach ($matches[1] as $reference) {
                $reference = trim($reference, " \t\n\r\0\x0B'\"");

                if ($this->isExternalReference($reference)) {
                    return true;
                }
            }
        }

        // CSS `@import "https://..."` / `@import url(...)` can pull external
        // resources without a url() wrapper, so block the at-rule entirely.
        return preg_match('/@import\b/i', $value) === 1;
    }

    /**
     * @param list<string> $allowlist
     *
     * @return list<string>
     */
    private function normalizeAllowlist(array $allowlist): array
    {
        return array_values(array_unique(array_map(static fn (string $value) => mb_strtolower($value), $allowlist)));
    }
}
