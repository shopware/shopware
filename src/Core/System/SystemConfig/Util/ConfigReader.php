<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig\Util;

use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Util\XmlReader;
use Shopware\Core\System\SystemConfig\Exception\BundleConfigNotFoundException;

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
    public function getConfigFromBundle(Bundle $bundle, ?string $bundleConfigPath = null): array
    {
        $bundleConfigPath = $bundleConfigPath ?? $bundle->getConfigPath();
        $configPath = $bundle->getPath() . '/' . ltrim($bundleConfigPath, '/');

        if (!is_file($configPath)) {
            throw new BundleConfigNotFoundException($bundleConfigPath, $bundle->getName());
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

        /** @var \DOMElement $element */
        foreach ($xml->getElementsByTagName('card') as $element) {
            $cardDefinitions[] = [
                'title' => $this->getCardTitles($element),
                'elements' => $this->getElements($element),
            ];
        }

        return $cardDefinitions;
    }

    private function getCardTitles(\DOMElement $element): array
    {
        $titles = [];
        /** @var \DOMElement $title */
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
            if ($element->nodeName === 'title') {
                continue;
            }

            $elements[$count] = $this->elementToArray($element);
            ++$count;
        }

        return $elements;
    }

    private function elementToArray(\DOMElement $element): array
    {
        $options = static::getAllChildren($element);

        if ($element->nodeName === 'component') {
            $elementData = [
                'componentName' => $element->getAttribute('name'),
            ];

            foreach ($options as $option) {
                $elementData[$option->nodeName] = $option->nodeValue;
            }

            return $elementData;
        }

        $swFieldType = $element->getAttribute('type') ?: 'text';

        $elementData = [
            'type' => $swFieldType,
        ];

        foreach ($options as $option) {
            if ($this->isTranslateAbleOption($option)) {
                $elementData[$option->nodeName][$this->getLocaleCodeFromElement($option)] = $option->nodeValue;
                continue;
            }

            if ($this->isBoolOption($option)) {
                $elementData[$option->nodeName] = filter_var($option->nodeValue, FILTER_VALIDATE_BOOLEAN);
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

        /** @var \DOMElement $option */
        foreach ($element->getElementsByTagName('option') as $option) {
            $options[] = [
                'id' => $option->getElementsByTagName('id')->item(0)->nodeValue,
                'name' => $this->getOptionLabels($option),
            ];
        }

        return $options;
    }

    private function getOptionLabels(\DOMElement $option): array
    {
        $optionLabels = [];

        /** @var \DOMElement $label */
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
        return \in_array($option->nodeName, ['copyable', 'disabled'], true);
    }

    private function elementIsOptions(\DOMElement $option): bool
    {
        return $option->nodeName === 'options';
    }
}
