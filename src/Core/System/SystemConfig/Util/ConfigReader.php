<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig\Util;

use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\XmlReader;
use Shopware\Core\System\SystemConfig\Exception\BundleConfigNotFoundException;

#[Package('system-settings')]
class ConfigReader extends XmlReader
{
    private const FALLBACK_LOCALE = 'en-GB';

    /**
     * @var string
     */
    protected $xsdFile = __DIR__ . '/../Schema/config.xsd';

    /**
     * @throws BundleConfigNotFoundException
     */
    public function getConfigFromBundle(Bundle $bundle, ?string $bundleConfigName = null): array
    {
        if ($bundleConfigName === null) {
            $bundleConfigName = 'Resources/config/config.xml';
        } else {
            $bundleConfigName = 'Resources/config/' . preg_replace('/\\.xml$/i', '', $bundleConfigName) . '.xml';
        }
        $configPath = $bundle->getPath() . '/' . ltrim($bundleConfigName, '/');

        if (!is_file($configPath)) {
            throw new BundleConfigNotFoundException($bundleConfigName, $bundle->getName());
        }

        return $this->read($configPath);
    }

    /**
     * This method is the main entry point to parse a xml file.
     */
    protected function parseFile(\DOMDocument $xml): array
    {
        return $this->getCardDefinitions($xml);
    }

    private function getCardDefinitions(\DOMDocument $xml): array
    {
        $cardDefinitions = [];

        foreach ($xml->getElementsByTagName('card') as $index => $element) {
            $cardDefinitions[] = [
                'title' => $this->getCardTitles($element),
                'name' => $this->getCardName($element),
                'elements' => $this->getElements($element),
            ];

            if ($this->getCardFlag($element) !== null) {
                $cardDefinitions[$index]['flag'] = $this->getCardFlag($element);
            }
        }

        return $cardDefinitions;
    }

    private function getCardTitles(\DOMElement $element): array
    {
        $titles = [];
        foreach ($element->getElementsByTagName('title') as $title) {
            $titles[$this->getLocaleCodeFromElement($title)] = $title->nodeValue;
        }

        return $titles;
    }

    private function getElements(\DOMElement $xml): array
    {
        $elements = [];
        $count = 0;
        /** @var \DOMElement $element */
        foreach (static::getAllChildren($xml) as $element) {
            $nodeName = $element->nodeName;
            if ($nodeName === 'title' || $nodeName === 'name' || $nodeName === 'flag') {
                continue;
            }

            $elements[$count] = $this->elementToArray($element);
            ++$count;
        }

        return $elements;
    }

    private function getCardName(\DOMElement $element): ?string
    {
        foreach ($element->getElementsByTagName('name') as $name) {
            $parentNode = $name->parentNode;
            if (($parentNode !== null) && $parentNode->nodeName !== 'card') {
                continue;
            }

            return $name->nodeValue;
        }

        return null;
    }

    private function getCardFlag(\DOMElement $element): ?string
    {
        foreach ($element->getElementsByTagName('flag') as $flag) {
            $parentNode = $flag->parentNode;
            if (($parentNode !== null) && $parentNode->nodeName !== 'card') {
                continue;
            }

            return $flag->nodeValue;
        }

        return null;
    }

    private function elementToArray(\DOMElement $element): array
    {
        $options = static::getAllChildren($element);

        if ($element->nodeName === 'component') {
            return $this->getElementDataForComponent($element, $options);
        }

        return $this->getElementDataForInputField($element, $options);
    }

    /**
     * @param array<\DOMElement> $options
     */
    private function getElementDataForComponent(\DOMElement $element, array $options): array
    {
        $elementData = [
            'componentName' => $element->getAttribute('name'),
        ];

        return $this->addOptionsToElementData($options, $elementData);
    }

    private function getElementDataForInputField(\DOMElement $element, array $options): array
    {
        $swFieldType = $element->getAttribute('type') ?: 'text';

        $elementData = [
            'type' => $swFieldType,
        ];

        return $this->addOptionsToElementData($options, $elementData);
    }

    /**
     * @param array<\DOMElement> $options
     * @param array<string, mixed> $elementData
     *
     * @return array<string, mixed>
     */
    private function addOptionsToElementData(array $options, array $elementData): array
    {
        foreach ($options as $option) {
            if ($this->isTranslateAbleOption($option)) {
                $elementData[$option->nodeName][$this->getLocaleCodeFromElement($option)] = $option->nodeValue;

                continue;
            }

            if ($this->isBoolOption($option)) {
                $elementData[$option->nodeName] = filter_var($option->nodeValue, \FILTER_VALIDATE_BOOLEAN);

                continue;
            }

            if ($this->elementIsOptions($option)) {
                $elementData['options'] = $this->optionsToArray($option);

                continue;
            }

            $elementData[$option->nodeName] = $option->nodeValue;
        }

        return $elementData;
    }

    private function optionsToArray(\DOMElement $element): array
    {
        $options = [];

        foreach ($element->getElementsByTagName('option') as $option) {
            $idTag = $option->getElementsByTagName('id')->item(0);
            if ($idTag === null) {
                continue;
            }

            $options[] = [
                'id' => $idTag->nodeValue,
                'name' => $this->getOptionLabels($option),
            ];
        }

        return $options;
    }

    private function getOptionLabels(\DOMElement $option): array
    {
        $optionLabels = [];

        foreach ($option->getElementsByTagName('name') as $label) {
            $optionLabels[$this->getLocaleCodeFromElement($label)] = $label->nodeValue;
        }

        return $optionLabels;
    }

    private function getLocaleCodeFromElement(\DOMElement $element): string
    {
        return $element->getAttribute('lang') ?: self::FALLBACK_LOCALE;
    }

    private function isTranslateAbleOption(\DOMElement $option): bool
    {
        return \in_array($option->nodeName, ['label', 'placeholder', 'helpText'], true);
    }

    private function isBoolOption(\DOMElement $option): bool
    {
        return \in_array($option->nodeName, ['copyable', 'disabled', 'required'], true);
    }

    private function elementIsOptions(\DOMElement $option): bool
    {
        return $option->nodeName === 'options';
    }
}
