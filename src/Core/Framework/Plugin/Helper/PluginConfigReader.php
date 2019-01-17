<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Helper;

use DOMDocument;
use DOMElement;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Util\XmlReader;

class PluginConfigReader extends XmlReader
{
    private const TRANSLATEABLE_OPTIONS = ['label', 'placeholder', 'helpText'];
    private const CARD_SELECTOR = 'card';
    private const DEFINED_FIELDS = 'fields';
    private const TITLE_SELECTOR = 'title';
    private const FIELD_SELECTOR = 'sw-field';
    private const TYPE_SELECTOR = 'type';
    private const TEXT_TYPE = 'text';
    private const LANG_SELECTOR = 'lang';
    private const BOOL_OPTIONS = ['copyable', 'disabled'];
    private const OPTIONS_SELECTOR = 'options';
    private const OPTION_SELECTOR = 'option';
    protected $xsdFile = __DIR__ . '/../Schema/config.xsd';

    /**
     * This method is the main entry point to parse a xml file.
     */
    protected function parseFile(DOMDocument $xml): array
    {
        return $this->getCardDefinitions($xml);
    }

    private function getCardDefinitions(DOMDocument $xml): array
    {
        $cardDefinitions = [];

        /** @var DOMElement $element */
        foreach ($xml->getElementsByTagName(self::CARD_SELECTOR) as $element) {
            $cardDefinitions[] = [
                self::TITLE_SELECTOR => $this->getCardTitles($element),
                self::DEFINED_FIELDS => $this->getSwFieldDefinitions($element),
            ];
        }

        return $cardDefinitions;
    }

    private function getCardTitles(DOMElement $element): array
    {
        $titles = [];
        /** @var DOMElement $title */
        foreach ($element->getElementsByTagName(self::TITLE_SELECTOR) as $title) {
            $titles[$this->getLocaleCodeFromElement($title)] = $title->nodeValue;
        }

        return $titles;
    }

    private function getSwFieldDefinitions(DOMElement $xml): array
    {
        $swFieldDefinitions = [];
        $count = 0;
        /** @var DOMElement $element */
        foreach ($xml->getElementsByTagName(self::FIELD_SELECTOR) as $element) {
            $swFieldDefinitions[$count] = $this->swFieldDefinitionToArray($element);
            $swFieldDefinitions[$count]['value'] = null;
            ++$count;
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
                $swFieldArray[$option->localName][$this->getLocaleCodeFromElement($option)] = $option->nodeValue;
                continue;
            }

            if ($this->isBoolOption($option)) {
                $swFieldArray[$option->localName] = filter_var($option->nodeValue, FILTER_VALIDATE_BOOLEAN);
                continue;
            }

            if ($this->elementIsOptions($option)) {
                $swFieldArray[self::OPTIONS_SELECTOR] = $this->optionsToArray($option);
                continue;
            }

            $swFieldArray[$option->localName] = $option->nodeValue;
        }

        return $swFieldArray;
    }

    private function optionsToArray(DOMElement $element): array
    {
        $options = [];

        /** @var DOMElement $option */
        foreach ($element->getElementsByTagName(self::OPTION_SELECTOR) as $option) {
            $options[] = [
                'value' => $option->getElementsByTagName('value')->item(0)->nodeValue,
                'label' => $this->getOptionLabels($option),
            ];
        }

        return $options;
    }

    private function getOptionLabels(DOMElement $option): array
    {
        $optionLabels = [];

        /** @var DOMElement $label */
        foreach ($option->getElementsByTagName('label') as $label) {
            $optionLabels[$this->getLocaleCodeFromElement($label)] = $label->nodeValue;
        }

        return $optionLabels;
    }

    private function getLocaleCodeFromElement(DOMElement $element): string
    {
        return $element->getAttribute(self::LANG_SELECTOR) ?: Defaults::LOCALE_EN_GB_ISO;
    }

    private function isTranslateAbleOption(DOMElement $option): bool
    {
        return \in_array($option->localName, self::TRANSLATEABLE_OPTIONS, true);
    }

    private function isBoolOption(DOMElement $option): bool
    {
        return \in_array($option->localName, self::BOOL_OPTIONS, true);
    }

    private function elementIsOptions(DOMElement $option): bool
    {
        return $option->localName === self::OPTIONS_SELECTOR;
    }
}
