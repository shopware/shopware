<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Util;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\Exception\XmlElementNotFoundException;
use Shopware\Core\System\SystemConfig\Exception\XmlParsingException;
use Symfony\Component\Config\Util\XmlUtils;

#[Package('core')]
abstract class XmlReader
{
    /**
     * @var string should be set in instance that extends this class
     */
    protected $xsdFile;

    /**
     * load and validate xml file - parse to array
     *
     * @throws XmlParsingException
     */
    public function read(string $xmlFile): array
    {
        try {
            $dom = XmlUtils::loadFile($xmlFile, $this->xsdFile);
        } catch (\Exception $e) {
            throw new XmlParsingException($xmlFile, $e->getMessage());
        }

        return $this->parseFile($dom);
    }

    public static function getAllChildren(\DOMNode $node): array
    {
        $children = [];
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $children[] = $child;
            }
        }

        return $children;
    }

    public static function getChildByName(\DOMNode $node, string $name): array
    {
        $children = [];
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === $name) {
                $children[] = $child;
            }
        }

        return $children;
    }

    public static function getFirstChildren(\DOMNode $list, string $name): ?\DOMElement
    {
        $children = self::getChildByName($list, $name);

        if (\count($children) === 0) {
            return null;
        }

        return $children[0];
    }

    public static function validateBooleanAttribute(string $value, bool $defaultValue = false): bool
    {
        if ($value === '') {
            return $defaultValue;
        }

        return (bool) static::phpize($value);
    }

    public static function parseOptionsNodeList(\DOMNodeList $optionsList): ?array
    {
        if ($optionsList->length === 0) {
            return null;
        }

        $optionList = $optionsList->item(0)->childNodes;

        if ($optionList->length === 0) {
            return null;
        }

        $options = [];

        /** @var \DOMElement $option */
        foreach ($optionList as $option) {
            if ($option instanceof \DOMElement) {
                $options[$option->nodeName] = static::phpize($option->nodeValue);
            }
        }

        return $options;
    }

    /**
     * @throws XmlElementNotFoundException
     */
    public static function getElementChildValueByName(\DOMElement $element, string $name, bool $throwException = false): ?string
    {
        $children = $element->getElementsByTagName($name);

        if ($children->length === 0) {
            if ($throwException) {
                throw new XmlElementNotFoundException($name);
            }

            return null;
        }

        return $children->item(0)->nodeValue;
    }

    public static function validateTextAttribute(string $type, string $defaultValue = ''): string
    {
        if ($type === '') {
            return $defaultValue;
        }

        return $type;
    }

    /**
     * @return mixed
     */
    public static function phpize(mixed $value)
    {
        $value = XmlUtils::phpize($value);

        if (!\is_string($value)) {
            return $value;
        }

        try {
            return json_decode($value, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            // do nothing, return unparsed value
        }

        return $value;
    }

    /**
     * This method is the main entry point to parse a xml file.
     */
    abstract protected function parseFile(\DOMDocument $xml): array;
}
