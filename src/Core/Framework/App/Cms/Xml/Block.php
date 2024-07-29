<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Cms\Xml;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopware\Core\Framework\App\Manifest\XmlParserUtils;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @phpstan-type BlockArray array{
 *           name: string,
 *           category: string,
 *           label: array<string, string>,
 *           slots: array<string, array{
 *               type: string,
 *               default: array{
 *                   config: array<string, array{
 *                       source: string,
 *                       value: string
 *                   }>
 *               }
 *           }>,
 *           defaultConfig: array<string, array{
 *               source: string,
 *               value: string
 *           }>
 *      }
 */
#[Package('buyers-experience')]
class Block extends XmlElement
{
    private const TRANSLATABLE_FIELDS = [
        'label',
    ];

    protected string $name;

    protected string $category;

    /**
     * @var array<string, string>
     */
    protected array $label = [];

    /**
     * @var list<Slot>
     */
    protected array $slots = [];

    protected DefaultConfig $defaultConfig;

    public function toArray(string $defaultLocale): array
    {
        $data = parent::toArray($defaultLocale);

        foreach (self::TRANSLATABLE_FIELDS as $TRANSLATABLE_FIELD) {
            $translatableField = self::kebabCaseToCamelCase($TRANSLATABLE_FIELD);

            $data[$translatableField] = $this->ensureTranslationForDefaultLanguageExist(
                $data[$translatableField],
                $defaultLocale
            );
        }

        return $data;
    }

    /**
     * @return array{
     *     appId: string,
     *     name: string,
     *     label: array<string, string>,
     *     block: BlockArray
     * }
     */
    public function toEntityArray(string $appId, string $defaultLocale): array
    {
        $slots = [];

        foreach ($this->slots as $slot) {
            $slots[$slot->getName()] = [
                'type' => $slot->getType(),
                'default' => [
                    'config' => $slot->getConfig()->toArray($defaultLocale),
                ],
            ];
        }

        return [
            'appId' => $appId,
            'name' => $this->getName(),
            'label' => $this->ensureTranslationForDefaultLanguageExist($this->getLabel(), $defaultLocale),
            'block' => [
                'name' => $this->getName(),
                'category' => $this->getCategory(),
                'label' => $this->ensureTranslationForDefaultLanguageExist($this->getLabel(), $defaultLocale),
                'slots' => $slots,
                'defaultConfig' => $this->defaultConfig->toArray($defaultLocale),
            ],
        ];
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    /**
     * @return array<string, string>
     */
    public function getLabel(): array
    {
        return $this->label;
    }

    /**
     * @return list<Slot>
     */
    public function getSlots(): array
    {
        return $this->slots;
    }

    public function getDefaultConfig(): DefaultConfig
    {
        return $this->defaultConfig;
    }

    protected static function parse(\DOMElement $element): array
    {
        $values = [];

        foreach ($element->childNodes as $block) {
            if (!$block instanceof \DOMElement) {
                continue;
            }

            $values = self::parseChild($block, $values);
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    private static function parseChild(\DOMElement $child, array $values): array
    {
        // translated
        if (\in_array($child->tagName, self::TRANSLATABLE_FIELDS, true)) {
            return XmlParserUtils::mapTranslatedTag($child, $values);
        }

        if ($child->tagName === 'slots') {
            $values[$child->tagName] = XmlParserUtils::parseChildrenAsList(
                $child,
                static fn (\DOMElement $element): Slot => Slot::fromXml($element)
            );

            return $values;
        }

        if ($child->tagName === 'default-config') {
            $values[XmlParserUtils::kebabCaseToCamelCase($child->tagName)] = DefaultConfig::fromXml($child);

            return $values;
        }

        $values[XmlParserUtils::kebabCaseToCamelCase($child->tagName)] = $child->nodeValue;

        return $values;
    }
}
