<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Helper;

use DOMDocument;
use DOMElement;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Util\XmlReader;

class PluginConfigReader extends XmlReader
{
    private const TRANSLATEABLE_OPTIONS = ['label', 'placeholder', 'helpText'];
    private const FIELD_SELECTOR = 'sw-field';
    private const TYPE_SELECTOR = 'type';
    private const TEXT_TYPE = 'text';
    private const LANG_SELECTOR = 'lang';
    protected $xsdFile = __DIR__ . '/../Schema/config.xsd';

    /**
     * This method is the main entry point to parse a xml file.
     */
    protected function parseFile(DOMDocument $xml): array
    {
        return $this->getSwFieldDefinitions($xml);
    }

    private function getSwFieldDefinitions(DOMDocument $xml): array
    {
        $swFieldDefinitions = [];
        /** @var DOMElement $element */
        foreach ($xml->getElementsByTagName(self::FIELD_SELECTOR) as $element) {
            $swFieldDefinitions[] = $this->swFieldDefinitionToArray($element);
        }

        return $swFieldDefinitions;
    }

    private function swFieldDefinitionToArray(DOMElement $swField): array
    {
        $swFieldType = $swField->getAttribute(self::TYPE_SELECTOR) ?: self::TEXT_TYPE;
        /** @var DOMElement[] $options */
        $options = self::getAllChildren($swField);
        $swFieldArray = [
            self::TYPE_SELECTOR => $swFieldType,
        ];

        foreach ($options as $option) {
            if ($this->isTranslateAbleOption($option)) {
                $swFieldArray[$option->localName][$option->getAttribute(self::LANG_SELECTOR) ?: Defaults::LOCALE_EN_GB_ISO] = $option->nodeValue;
            } else {
                $swFieldArray[$option->localName] = $option->nodeValue;
            }
        }

        return $swFieldArray;
    }

    private function isTranslateAbleOption(DOMElement $option): bool
    {
        return \in_array($option->localName, self::TRANSLATEABLE_OPTIONS, true);
    }
}
