<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml\Document;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopware\Core\Framework\App\Manifest\XmlParserUtils;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class DocumentType extends XmlElement
{
    protected const REQUIRED_FIELDS = [
        'identifier',
        'label',
        'formats',
    ];

    private const TRANSLATABLE_FIELDS = [
        'label',
    ];

    protected string $identifier;

    /**
     * @var array<string, string>
     */
    protected array $label = [];

    /**
     * @var list<string>
     */
    protected array $formats = [];

    /**
     * @var array<string, scalar>
     */
    protected array $config = [];

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * @return array<string, string>
     */
    public function getLabel(): array
    {
        return $this->label;
    }

    /**
     * @return list<string>
     */
    public function getFormats(): array
    {
        return $this->formats;
    }

    /**
     * @return array<string, scalar>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    protected static function parse(\DOMElement $element): array
    {
        $values = XmlParserUtils::parseChildrenAndTranslate($element, self::TRANSLATABLE_FIELDS);

        $formatsElement = $element->getElementsByTagName('formats')->item(0);
        unset($values['formats']);

        if ($formatsElement instanceof \DOMElement) {
            $formats = [];

            foreach ($formatsElement->getElementsByTagName('format') as $format) {
                $formats[] = (string) $format->nodeValue;
            }

            $values['formats'] = $formats;
        }

        $config = $element->getElementsByTagName('config')->item(0);
        unset($values['config']);

        $values['config'] = $config instanceof \DOMElement
            ? DocumentTypeConfig::fromXml($config)
            : [];

        return $values;
    }
}
