<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig\Helper;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Util\XmlReader;
use Shopware\Core\System\SystemConfig\Exception\BundleConfigNotFoundException;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

class ConfigReader extends XmlReader
{
    /**
     * @var string
     */
    protected $xsdFile = __DIR__ . '/../Schema/config.xsd';

    /**
     * @throws BundleConfigNotFoundException
     */
    public function getConfigFromBundle(BundleInterface $bundle): array
    {
        $configPath = $bundle->getPath() . '/Resources/config.xml';

        if (!is_file($configPath)) {
            throw new BundleConfigNotFoundException($bundle->getName());
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
                'fields' => $this->getSwFieldDefinitions($element),
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

    private function getSwFieldDefinitions(\DOMElement $xml): array
    {
        $swFieldDefinitions = [];
        $count = 0;
        /** @var \DOMElement $element */
        foreach ($xml->getElementsByTagName('input-field') as $element) {
            $swFieldDefinitions[$count] = $this->swFieldDefinitionToArray($element);
            $swFieldDefinitions[$count]['value'] = null;
            ++$count;
        }

        return $swFieldDefinitions;
    }

    private function swFieldDefinitionToArray(\DOMElement $swField): array
    {
        $swFieldType = $swField->getAttribute('type') ?: 'text';
        /** @var \DOMElement[] $options */
        $options = self::getAllChildren($swField);
        $swFieldArray = [
            'type' => $swFieldType,
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
                $swFieldArray['options'] = $this->optionsToArray($option);
                continue;
            }

            $swFieldArray[$option->localName] = $option->nodeValue;
        }

        return $swFieldArray;
    }

    private function optionsToArray(\DOMElement $element): array
    {
        $options = [];

        /** @var \DOMElement $option */
        foreach ($element->getElementsByTagName('option') as $option) {
            $options[] = [
                'value' => $option->getElementsByTagName('value')->item(0)->nodeValue,
                'label' => $this->getOptionLabels($option),
            ];
        }

        return $options;
    }

    private function getOptionLabels(\DOMElement $option): array
    {
        $optionLabels = [];

        /** @var \DOMElement $label */
        foreach ($option->getElementsByTagName('label') as $label) {
            $optionLabels[$this->getLocaleCodeFromElement($label)] = $label->nodeValue;
        }

        return $optionLabels;
    }

    private function getLocaleCodeFromElement(\DOMElement $element): string
    {
        return $element->getAttribute('lang') ?: Defaults::LOCALE_EN_GB_ISO;
    }

    private function isTranslateAbleOption(\DOMElement $option): bool
    {
        return \in_array($option->localName, ['label', 'placeholder', 'helpText'], true);
    }

    private function isBoolOption(\DOMElement $option): bool
    {
        return \in_array($option->localName, ['copyable', 'disabled'], true);
    }

    private function elementIsOptions(\DOMElement $option): bool
    {
        return $option->localName === 'options';
    }
}
