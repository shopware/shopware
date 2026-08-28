<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\File;

use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Contracts\Service\ResetInterface;

#[Package('discovery')]
class SvgContentValidator extends AbstractFileContentValidator implements ResetInterface
{
    private const SVG = 'svg';
    private const STYLE = 'style';
    private const METADATA = 'metadata';
    private const JSON = 'json';
    private const IMAGE = 'image';
    private const ACTIVE_CONTENT_MESSAGE = 'SVG files with active content are not allowed.';
    private const INVALID_SVG_MESSAGE = 'The file is not a valid SVG document.';
    private const PARSE_ERROR_MESSAGE = 'The SVG file could not be parsed.';
    private const SVG_NAMESPACE = 'http://www.w3.org/2000/svg';
    private const XLINK_NAMESPACE = 'http://www.w3.org/1999/xlink';
    private const XMLNS_NAMESPACE = 'http://www.w3.org/2000/xmlns/';
    private const XML_NAMESPACE = 'http://www.w3.org/XML/1998/namespace';
    private const RDF_NAMESPACE = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';
    private const CC_NAMESPACE = 'http://creativecommons.org/ns#';
    private const DC_NAMESPACE = 'http://purl.org/dc/elements/1.1/';
    private const SODIPODI_NAMESPACE = 'http://sodipodi.sourceforge.net/DTD/sodipodi-0.dtd';
    private const INKSCAPE_NAMESPACE = 'http://www.inkscape.org/namespaces/inkscape';
    private const BOXY_SVG_NAMESPACE = 'https://boxy-svg.com';
    private const SKETCH_NAMESPACE = 'http://www.bohemiancoding.com/sketch/ns';
    private const XPACKET_PROCESSING_INSTRUCTION = 'xpacket';
    private const DISALLOWED_NODE_TYPE = 'Node types not allowed';
    private const DISALLOWED_ELEMENT = 'Elements not allowed';
    private const DISALLOWED_EVENT_HANDLER_ATTRIBUTE = 'Event handler attributes not allowed';
    private const DISALLOWED_ATTRIBUTE = 'Attributes not allowed';
    private const DISALLOWED_ANIMATED_ATTRIBUTE = 'Animated attributes not allowed';
    private const DISALLOWED_EXTERNAL_REFERENCE = 'External references not allowed';
    private const DISALLOWED_EXTERNAL_STYLE_REFERENCE = 'External style references not allowed';
    private const ANIMATE = 'animate';
    private const ANIMATE_TRANSFORM = 'animatetransform';

    /**
     * @var list<string>
     */
    private const ALLOWED_ANIMATED_ATTRIBUTES = [
        'cx',
        'cy',
        'fill',
        'fill-opacity',
        'height',
        'opacity',
        'r',
        'rx',
        'ry',
        'stroke-opacity',
        'stroke-width',
        'width',
        'x',
        'y',
    ];

    /**
     * @var array<string, list<string>>
     */
    private const ALLOWED_SVG_DOCTYPES = [
        '-//W3C//DTD SVG 1.0//EN' => ['http://www.w3.org/TR/2001/REC-SVG-20010904/DTD/svg10.dtd'],
        '-//W3C//DTD SVG 20010904//EN' => ['http://www.w3.org/TR/2001/REC-SVG-20010904/DTD/svg10.dtd'],
        '-//W3C//DTD SVG 1.1//EN' => ['http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd'],
        '-//W3C//DTD SVG 1.1 Basic//EN' => ['http://www.w3.org/Graphics/SVG/1.1/DTD/svg11-basic.dtd'],
    ];

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

    private ConstraintViolationList $violations;

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
        $this->violations = new ConstraintViolationList();
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
        $this->reset();

        if ($this->supports($mediaFile) === false) {
            return;
        }

        $previousErrorHandling = $this->captureLibxmlErrors();

        try {
            $reader = $this->openSvgReader($mediaFile);

            try {
                $this->validateDocument($reader, $this->hasAllowedSvgDoctype($mediaFile));
            } finally {
                $reader->close();
            }

            if ($this->hasCollectedLibxmlErrors()) {
                throw MediaException::invalidFile(self::PARSE_ERROR_MESSAGE);
            }

            if ($this->violations->count() > 0) {
                throw MediaException::invalidFile($this->getViolationsMessage());
            }
        } finally {
            $this->restoreLibxmlErrorHandling($previousErrorHandling);
        }
    }

    public function reset(): void
    {
        $this->violations = new ConstraintViolationList();
    }

    private function validateDocument(\XMLReader $reader, bool $hasAllowedSvgDoctype): void
    {
        $documentElementSeen = false;
        $metadataDepth = 0;

        while ($reader->read()) {
            if ($reader->nodeType === \XMLReader::END_ELEMENT && $this->isUnprefixedSvgElement($reader, self::METADATA) && $metadataDepth > 0) {
                --$metadataDepth;

                continue;
            }

            if ($reader->nodeType === \XMLReader::ELEMENT && $this->isUnprefixedSvgElement($reader, self::METADATA) && $metadataDepth > 0) {
                if (!$reader->isEmptyElement) {
                    ++$metadataDepth;
                }

                continue;
            }

            if ($metadataDepth > 0) {
                $this->validateMetadataNode($reader, $hasAllowedSvgDoctype);

                continue;
            }

            if ($this->isDisallowedNodeType($reader, $hasAllowedSvgDoctype)) {
                $this->buildViolation(self::DISALLOWED_NODE_TYPE, $reader->name);
            }

            if ($reader->nodeType !== \XMLReader::ELEMENT) {
                continue;
            }

            if ($documentElementSeen === false) {
                $this->validateRootElement($reader);
                $documentElementSeen = true;
            }

            $this->validateElement($reader);

            if (!$reader->isEmptyElement && $this->isUnprefixedSvgElement($reader, self::METADATA)) {
                ++$metadataDepth;
            }
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

        $allowsAnyPassiveAttribute = $this->isKnownPassiveNamespaceNode($reader);

        $this->assertElementAllowed($elementName, $allowsAnyPassiveAttribute);
        $this->assertAttributesAllowed($reader, $allowsAnyPassiveAttribute, $elementName);
        $this->assertStyleBodyAllowed($reader, $elementName);
        $this->assertAnimationAllowed($reader, $elementName);
    }

    private function validateMetadataNode(\XMLReader $reader, bool $hasAllowedSvgDoctype): void
    {
        if ($this->isDisallowedNodeType($reader, $hasAllowedSvgDoctype, true)) {
            $this->buildViolation(self::DISALLOWED_NODE_TYPE, $reader->name);
        }

        if ($reader->nodeType !== \XMLReader::ELEMENT) {
            return;
        }

        if (!$this->isSvgElement($reader)) {
            return;
        }

        if ($this->isUnprefixedSvgElement($reader, self::JSON)) {
            $this->assertAttributesAllowed($reader, false, self::JSON);

            return;
        }

        $this->validateElement($reader);
    }

    private function assertElementAllowed(string $elementName, bool $allowsAnyPassiveElement): void
    {
        if (!$allowsAnyPassiveElement && !\in_array($elementName, $this->allowedElements, true)) {
            $this->buildViolation(self::DISALLOWED_ELEMENT, $elementName);
        }
    }

    private function assertAttributesAllowed(\XMLReader $reader, bool $allowsAnyPassiveAttribute, string $elementName): void
    {
        if (!$reader->hasAttributes) {
            return;
        }

        $attributePosition = $reader->moveToFirstAttribute();
        while ($attributePosition === true) {
            $this->assertAttributeAllowed($reader, $allowsAnyPassiveAttribute, $elementName);
            $attributePosition = $reader->moveToNextAttribute();
        }

        $reader->moveToElement();
    }

    private function assertAttributeAllowed(\XMLReader $reader, bool $allowsAnyPassiveAttribute, string $elementName): void
    {
        $attributeName = mb_strtolower($reader->name);

        if ($this->isEventHandlerAttribute($attributeName)) {
            $this->buildViolation(self::DISALLOWED_EVENT_HANDLER_ATTRIBUTE, $attributeName);
        }

        if (!$this->isAllowedAttribute($reader, $attributeName, $allowsAnyPassiveAttribute)) {
            $this->buildViolation(self::DISALLOWED_ATTRIBUTE, $attributeName);
        }

        $isReferenceAttribute = \in_array($attributeName, $this->allowedReferenceAttributes, true);
        if ($isReferenceAttribute && $this->isExternalReference($reader->value) && !$this->isEmbeddedRasterImageReference($elementName, $reader->value)) {
            $this->buildViolation(self::DISALLOWED_EXTERNAL_REFERENCE, $attributeName);
        }

        if ($this->containsExternalStyleReference($reader->value)) {
            $this->buildViolation(self::DISALLOWED_EXTERNAL_STYLE_REFERENCE, $attributeName);
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
            $this->buildViolation(self::DISALLOWED_EXTERNAL_STYLE_REFERENCE, $elementName);
        }
    }

    private function assertAnimationAllowed(\XMLReader $reader, string $elementName): void
    {
        if (!\in_array($elementName, [self::ANIMATE, self::ANIMATE_TRANSFORM], true)) {
            return;
        }

        $animatedAttribute = $reader->getAttribute('attributeName');
        if ($animatedAttribute === null || trim($animatedAttribute) === '') {
            $this->buildViolation(self::DISALLOWED_ANIMATED_ATTRIBUTE, 'attributeName');

            return;
        }

        $animatedAttribute = mb_strtolower(trim($animatedAttribute));
        if ($elementName === self::ANIMATE_TRANSFORM && $animatedAttribute === 'transform') {
            return;
        }

        if ($elementName === self::ANIMATE && \in_array($animatedAttribute, self::ALLOWED_ANIMATED_ATTRIBUTES, true)) {
            return;
        }

        $this->buildViolation(self::DISALLOWED_ANIMATED_ATTRIBUTE, $animatedAttribute);
    }

    private function buildViolation(string $violation, string $invalidValue): void
    {
        $this->violations->add(new ConstraintViolation(
            $violation,
            '',
            [],
            null,
            '',
            $invalidValue
        ));
    }

    private function getViolationsMessage(): string
    {
        $valuesByMessage = [];
        foreach ($this->violations as $violation) {
            $valuesByMessage[(string) $violation->getMessage()][] = $violation->getInvalidValue();
        }

        $lines = [self::ACTIVE_CONTENT_MESSAGE];
        foreach ($valuesByMessage as $message => $values) {
            $lines[] = \sprintf('%s: %s', $message, implode(', ', array_unique($values)));
        }

        return implode(\PHP_EOL, $lines);
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

    private function isDisallowedNodeType(\XMLReader $reader, bool $hasAllowedSvgDoctype, bool $allowXpacketProcessingInstruction = false): bool
    {
        if ($reader->nodeType === \XMLReader::DOC_TYPE) {
            return !$hasAllowedSvgDoctype;
        }

        if ($allowXpacketProcessingInstruction && $reader->nodeType === \XMLReader::PI) {
            /*
             * XMP metadata wraps packets in <?xpacket ...?> processing instructions.
             * Keep every other processing instruction blocked.
             *
             * @see \Shopware\Tests\Unit\Core\Content\Media\File\SvgContentValidatorTest::testSvgWithXmpMetadataPassesValidation()
             */
            return mb_strtolower($reader->name) !== self::XPACKET_PROCESSING_INSTRUCTION;
        }

        return \in_array($reader->nodeType, [\XMLReader::ENTITY, \XMLReader::ENTITY_REF, \XMLReader::PI], true);
    }

    private function isExternalReference(string $value): bool
    {
        $value = trim($value);

        return $value !== '' && !str_starts_with($value, '#');
    }

    private function isEmbeddedRasterImageReference(string $elementName, string $value): bool
    {
        if ($elementName !== self::IMAGE) {
            return false;
        }

        $value = (string) preg_replace('/\s+/', '', trim($value));

        /*
         * Allows embedded raster image payloads only.
         * Regex tester: https://regex101.com/r/kxNJDI/1
         */
        return preg_match('/^data:image\/(?:png|jpe?g|gif|webp);base64,[a-z0-9+\/=]+$/i', $value) === 1;
    }

    private function isAllowedAttribute(\XMLReader $reader, string $attributeName, bool $allowsAnyPassiveAttribute): bool
    {
        if ($allowsAnyPassiveAttribute || str_starts_with($attributeName, 'data-') || $this->isKnownPassiveNamespaceNode($reader)) {
            return true;
        }

        if ($this->isNamespaceDeclaration($reader)) {
            return true;
        }

        if (!\in_array($attributeName, $this->allowedAttributes, true)) {
            return false;
        }

        if ($reader->prefix === 'xml') {
            return $reader->namespaceURI === self::XML_NAMESPACE;
        }

        if ($reader->prefix === 'xlink') {
            return $reader->namespaceURI === self::XLINK_NAMESPACE;
        }

        return $reader->namespaceURI === '' || $reader->namespaceURI === null;
    }

    private function isNamespaceDeclaration(\XMLReader $reader): bool
    {
        return $reader->namespaceURI === self::XMLNS_NAMESPACE && ($reader->prefix === 'xmlns' || $reader->name === 'xmlns');
    }

    private function isUnprefixedSvgElement(\XMLReader $reader, string $elementName): bool
    {
        return $reader->prefix === ''
            && mb_strtolower($reader->localName) === $elementName
            && $this->isSvgElement($reader);
    }

    private function isSvgElement(\XMLReader $reader): bool
    {
        return $reader->namespaceURI === '' || $reader->namespaceURI === self::SVG_NAMESPACE;
    }

    private function isKnownPassiveNamespaceNode(\XMLReader $reader): bool
    {
        return \in_array($reader->namespaceURI, [
            self::BOXY_SVG_NAMESPACE,
            self::CC_NAMESPACE,
            self::DC_NAMESPACE,
            self::INKSCAPE_NAMESPACE,
            self::RDF_NAMESPACE,
            self::SKETCH_NAMESPACE,
            self::SODIPODI_NAMESPACE,
        ], true);
    }

    private function hasAllowedSvgDoctype(MediaFile $mediaFile): bool
    {
        $prefix = file_get_contents($mediaFile->getFileName(), false, null, 0, 8192);
        if (!\is_string($prefix)) {
            return false;
        }

        $start = stripos($prefix, '<!DOCTYPE');
        if ($start === false) {
            return false;
        }

        $end = strpos($prefix, '>', $start);
        if ($end === false) {
            return false;
        }

        $doctype = substr($prefix, $start, $end - $start + 1);
        if (str_contains($doctype, '[')) {
            return false;
        }

        $doctype = (string) preg_replace('/\s+/', ' ', trim($doctype));
        if (!preg_match('/^<!DOCTYPE svg PUBLIC ([\'"])(.+?)\1 ([\'"])(.+?)\3\s*>$/i', $doctype, $matches)) {
            return false;
        }

        $publicId = $matches[2];
        $systemId = $matches[4];

        return \in_array($systemId, self::ALLOWED_SVG_DOCTYPES[$publicId] ?? [], true);
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
