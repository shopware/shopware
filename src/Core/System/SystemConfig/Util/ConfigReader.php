<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig\Util;

use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\XmlReader;
use Shopware\Core\System\SystemConfig\Exception\BundleConfigNotFoundException;
use Shopware\Core\System\SystemConfig\SystemConfigException;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * @phpstan-type CardDefinition array{title: array<string, string|null>, subtitle?: array<string, string|null>, name: string|null, elements: list<array<string, mixed>>, flag?: string|null}
 * @phpstan-type TabDefinition array{title: array<string, string|null>|null, name: string|null, cards: array<CardDefinition>}
 */
#[Package('framework')]
class ConfigReader extends XmlReader
{
    public const INPUT_TYPE_BOOL = 'bool';
    public const INPUT_TYPE_CHECKBOX = 'checkbox';
    public const INPUT_TYPE_INT = 'int';
    public const INPUT_TYPE_FLOAT = 'float';
    public const INPUT_TYPE_MULTI_SELECT = 'multi-select';

    private const FALLBACK_LOCALE = 'en-GB';

    protected string $xsdFile = __DIR__ . '/../Schema/config.xsd';

    /**
     * @throws BundleConfigNotFoundException
     *
     * @return array<array<string, mixed>>
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
            throw SystemConfigException::bundleConfigNotFound($bundleConfigName, $bundle->getName());
        }

        return $this->read($configPath);
    }

    /**
     * This method is the main entry point to parse a xml file.
     */
    protected function parseFile(\DOMDocument $xml): array
    {
        \assert($xml->firstChild instanceof \DOMElement);

        return (Feature::isActive('v6.8.0.0') || Feature::isActive('SYSTEM_CONFIG_TABS'))
            ? $this->getTabDefinitions($xml->firstChild)
            : $this->getCardDefinitions($xml->firstChild);
    }

    /**
     * @return array<TabDefinition>
     */
    private function getTabDefinitions(\DOMElement $xml): array
    {
        $tabDefinitions = [];
        $globalCardDefinitions = $this->getCardDefinitions($xml, true);

        if ($globalCardDefinitions !== []) {
            $tabDefinitions[] = [
                'title' => null,
                'name' => null,
                'cards' => $globalCardDefinitions,
            ];
        }

        $tabElements = $xml->getElementsByTagName('tab');

        if ($tabElements->length === 0) {
            return $tabDefinitions;
        }

        foreach ($tabElements as $element) {
            $tabDefinition = [
                'title' => $this->getTitles($element, 'tab'),
                'name' => $this->getName($element, 'tab'),
                'cards' => $this->getCardDefinitions($element),
            ];

            $tabDefinitions[] = $tabDefinition;
        }

        return $tabDefinitions;
    }

    /**
     * @return array<CardDefinition>
     */
    private function getCardDefinitions(\DOMElement $xml, bool $onlyGlobalTabs = false): array
    {
        $cardDefinitions = [];

        foreach ($xml->getElementsByTagName('card') as $element) {
            if ($onlyGlobalTabs && $element->parentNode?->nodeName === 'tab') {
                continue;
            }

            $cardDefinition = [
                'title' => $this->getTitles($element, 'card'),
                'name' => $this->getName($element, 'card'),
                'elements' => $this->getElements($element),
            ];

            if ($this->getCardSubtitles($element) !== []) {
                $cardDefinition['subtitle'] = $this->getCardSubtitles($element);
            }

            if ($this->getCardFlag($element) !== null) {
                $cardDefinition['flag'] = $this->getCardFlag($element);
            }

            $cardDefinitions[] = $cardDefinition;
        }

        return $cardDefinitions;
    }

    /**
     * @return array<string, string|null>
     */
    private function getTitles(\DOMElement $element, string $parentNodeName): array
    {
        $titles = [];

        foreach ($element->getElementsByTagName('title') as $title) {
            $parentNode = $title->parentNode;

            if (($parentNode !== null) && $parentNode->nodeName !== $parentNodeName) {
                continue;
            }

            $titles[$this->getLocaleCodeFromElement($title)] = $title->nodeValue;
        }

        return $titles;
    }

    /**
     * @return array<string, string|null>
     */
    private function getCardSubtitles(\DOMElement $element): array
    {
        $subtitles = [];
        foreach ($element->getElementsByTagName('subtitle') as $subtitle) {
            $subtitles[$this->getLocaleCodeFromElement($subtitle)] = $subtitle->nodeValue;
        }

        return $subtitles;
    }

    private function getName(\DOMElement $element, string $parentNodeName): ?string
    {
        foreach ($element->getElementsByTagName('name') as $name) {
            $parentNode = $name->parentNode;

            if (($parentNode !== null) && $parentNode->nodeName !== $parentNodeName) {
                continue;
            }

            return $name->nodeValue;
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getElements(\DOMElement $xml): array
    {
        $elements = [];
        foreach (static::getAllChildren($xml) as $element) {
            $nodeName = $element->nodeName;
            if (\in_array($nodeName, ['title', 'subtitle', 'name', 'flag'], true)) {
                continue;
            }

            $elements[] = $this->elementToArray($element);
        }

        return $elements;
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

    /**
     * @return array<string, mixed>
     */
    private function elementToArray(\DOMElement $element): array
    {
        $options = static::getAllChildren($element);

        if ($element->nodeName === 'component') {
            return $this->getElementDataForComponent($element, $options);
        }

        return $this->getElementDataForInputField($element, $options);
    }

    /**
     * @param list<\DOMElement> $options
     *
     * @return array<string, mixed>
     */
    private function getElementDataForComponent(\DOMElement $element, array $options): array
    {
        $elementData = [
            'componentName' => $element->getAttribute('name'),
        ];

        $elementData = $this->addCacheRelevantAttribute($element, $elementData);

        return $this->addOptionsToElementData($options, $elementData);
    }

    /**
     * @param list<\DOMElement> $options
     *
     * @return array<string, mixed>
     */
    private function getElementDataForInputField(\DOMElement $element, array $options): array
    {
        $swFieldType = $element->getAttribute('type') ?: 'text';

        $elementData = [
            'type' => $swFieldType,
        ];

        $elementData = $this->addCacheRelevantAttribute($element, $elementData);

        return $this->addOptionsToElementData($options, $elementData);
    }

    /**
     * @param array<string, mixed> $elementData
     *
     * @return array<string, mixed>
     */
    private function addCacheRelevantAttribute(\DOMElement $element, array $elementData): array
    {
        if (!$element->hasAttribute('cache-relevant')) {
            return $elementData;
        }

        $elementData['cacheRelevant'] = XmlUtils::phpize($element->getAttribute('cache-relevant'));

        return $elementData;
    }

    /**
     * @param list<\DOMElement> $options
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

            if ($option->nodeName === 'defaultValue') {
                $elementData[$option->nodeName] = $this->parseDefaultValue($option->nodeValue, $elementData['type'] ?? null);

                continue;
            }

            $elementData[$option->nodeName] = $option->nodeValue;
        }

        return $elementData;
    }

    private function parseDefaultValue(?string $value, ?string $type): mixed
    {
        $value = XmlReader::phpize($value);

        if ($value === null) {
            return null;
        }

        return match ($type) {
            // custom elements can have all types, there we can't guarantee the type
            null => $value,
            self::INPUT_TYPE_BOOL, self::INPUT_TYPE_CHECKBOX => (bool) $value,
            self::INPUT_TYPE_INT => (int) $value,
            self::INPUT_TYPE_FLOAT => (float) $value,
            self::INPUT_TYPE_MULTI_SELECT => (array) $value,
            default => (string) $value,
        };
    }

    /**
     * @return array<array{id: string|null, name: array<string, string|null>}>
     */
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

    /**
     * @return array<string, string|null>
     */
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
